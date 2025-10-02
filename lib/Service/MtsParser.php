<?php
namespace OCA\Metadata\Service;

class MtsParser extends FileReader {
	protected const STREAM_TYPE_VIDEO_MPEG2 = 0x02;
	protected const STREAM_TYPE_AUDIO_MPEG1 = 0x03;
	protected const STREAM_TYPE_VIDEO_H264  = 0x1B;
	protected const STREAM_TYPE_AUDIO_AC3   = 0x81;
	private const TARGET_STREAM_TYPES = array(
		self::STREAM_TYPE_VIDEO_MPEG2,
		self::STREAM_TYPE_AUDIO_MPEG1,
		self::STREAM_TYPE_VIDEO_H264,
		self::STREAM_TYPE_AUDIO_AC3
	);

	private const STREAM_ID_PROGRAM_STREAM_MAP  = 0b1011_1100;
	private const STREAM_ID_PADDING_STREAM      = 0b1011_1110;
	private const STREAM_ID_PRIVATE_STREAM_2    = 0b1011_1111;
	private const STREAM_ID_ECM_STREAM          = 0b1111_0000;
	private const STREAM_ID_EMM_STREAM          = 0b1111_0001;
	private const STREAM_ID_DSMCC_STREAM        = 0b1111_0010;
	private const STREAM_ID_H2221_TYPE_E_STREAM = 0b1111_1000;
	private const STREAM_ID_PROGRAM_STREAM_DIR  = 0b1111_1111;
	private const HEADERLESS_STREAM_IDS = array(
		self::STREAM_ID_PROGRAM_STREAM_MAP,
		self::STREAM_ID_PADDING_STREAM,
		self::STREAM_ID_PRIVATE_STREAM_2,
		self::STREAM_ID_ECM_STREAM,
		self::STREAM_ID_EMM_STREAM,
		self::STREAM_ID_DSMCC_STREAM,
		self::STREAM_ID_H2221_TYPE_E_STREAM,
		self::STREAM_ID_PROGRAM_STREAM_DIR
	);

	private $streamLimit;
	private $packetSize;
	private $pmtPids;
	private $pidStat;
	private $pidData;

	public function parseMts($hnd, $streamLimit = 0) {
		$this->streamLimit = $streamLimit;
		$this->packetSize = 0;
		$this->pmtPids = array();
		$this->pidStat = array();
		$this->pidData = array();
		$continue = true;

		while (!feof($hnd) && ($continue !== false)) {
			$continue = $this->readTsPacket($hnd);
		}

		foreach ($this->pidData as $pid => $data) {
			if (array_key_exists($pid, $this->pidStat) && ($this->pidStat[$pid] !== false)) {
				$this->handlePesData($pid, $data);
			}
		}
	}

	private function readTsPacket($hnd) {
		if ($this->packetSize !== 0) {
			$packet = fread($hnd, $this->packetSize);
		} else {
			$packet = fread($hnd, 192);
			if ($packet[0] === 'G') {
				$this->packetSize = 188;
				fseek($hnd, -4, SEEK_CUR);
			} else if ($packet[4] === 'G') {
				$this->packetSize = 192;
			} else {
				return false;	// no sync byte
			}
		}

		// Prefix (0-4 bytes)
		$pos = ($this->packetSize === 192) ? 4 : 0;

		// Header (4 bytes)
		if ((strlen($packet) < $this->packetSize) || ($packet[$pos] !== 'G')) {
			return false;	// no sync byte
		}

		$pid = $this->unpackShort(false, $packet, $pos + 1);
		$pusi = $pid & 0x4000;		// Payload Unit Start Indicator
		$pid = $pid & 0x1FFF;

		$afc = $this->unpackByte($packet, $pos + 3);
		$afc = ($afc & 0x30) >> 4;	// Adaptation Field Control

		// Payload
		$pos = $pos + 4;

		switch ($afc) {
			case 0:	// reserved
				return false;
			case 1:	// payload only
				break;
			case 2:	// no payload here, continue reading
				return true;
			case 3:	// adaptation plus payload
				$pos = $pos + $this->unpackByte($packet, $pos) + 1;
				break;
		}

		if (($pid === 0) || in_array($pid, $this->pmtPids)) {	// PSI table
			if ($pusi > 0) {	// start of payload unit
				$ptr = $this->unpackByte($packet, $pos);
				$pos = $pos + $ptr + 1;
				$this->pidData[$pid] = substr($packet, $pos);

			} else if (array_key_exists($pid, $this->pidData)) {
				$this->pidData[$pid] = $this->pidData[$pid] . substr($packet, $pos);
			}

			$section = $this->pidData[$pid];
			$len = strlen($section);
			if ($len > 12) {
				$sectionLength = ($this->unpackShort(false, $section, 1) & 0x0FFF) + 3;
				if ($len >= $sectionLength) {
					$crc = $this->unpackInt(false, $section, $sectionLength - 4);
					if ($crc === self::crc32($section, $sectionLength - 4)) {
						$tableId = $this->unpackByte($section);

						if (($pid === 0) && ($tableId === 0)) {	// PAT
							for ($p = 8; $p < $sectionLength - 4; $p += 4) {
								$programMapPid = $this->unpackShort(false, $section, $p + 2) & 0x1FFF;
								$this->pmtPids[] = $programMapPid;
							}

						} else if ($tableId === 2) {	// PMT
							$programInfoLength = $this->unpackShort(false, $section, 10) & 0x0FFF;

							for ($p = 12 + $programInfoLength; $p < $sectionLength - 4; ) {
								$streamType = $this->unpackByte($section, $p);
								$esId = $this->unpackShort(false, $section, $p + 1) & 0x1FFF;
								$esInfoLength = $this->unpackShort(false, $section, $p + 3) & 0x0FFF;
								$p += 5;

//								for ($e = 0; $e < $esInfoLength; ) {
//									$tag = $this->unpackByte($section, $p + $e);
//									$len = $this->unpackByte($section, $p + $e + 1);
//									$e = $e + 2 + $len;
//								}

								$p += $esInfoLength;

								if (in_array($streamType, self::TARGET_STREAM_TYPES) && !array_key_exists($esId, $this->pidStat)) {
									$this->pidStat[$esId] = $streamType;
								}
							}
						}
					}
				}
			}

		} else if ((!array_key_exists($pid, $this->pidStat) || ($this->pidStat[$pid] !== false)) && ($pid !== 0x1FFF)) {	// target PES data
			$continuePid = true;

			if ($pusi > 0) {
				if (array_key_exists($pid, $this->pidData) && array_key_exists($pid, $this->pidStat)) {
					$continuePid = $this->handlePesData($pid, $this->pidData[$pid]);
				}
				$this->pidData[$pid] = substr($packet, $pos);

			} else if (array_key_exists($pid, $this->pidData)) {
				$this->pidData[$pid] = $this->pidData[$pid] . substr($packet, $pos);
			}

			if (($this->streamLimit > 0) && array_key_exists($pid, $this->pidData) && (strlen($this->pidData[$pid]) > $this->streamLimit)) {
				$continuePid = array_key_exists($pid, $this->pidStat) ? $this->handlePesData($pid, $this->pidData[$pid]) : true;
				unset($this->pidData[$pid]);
			}

			if ($continuePid === false) {
				$this->pidStat[$pid] = false;
				unset($this->pidData[$pid]);
			}
		}

		if (count($this->pidStat) > 0) {
			foreach ($this->pidStat as $pid => $stat) {
				if ($stat !== false) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

	private function handlePesData($pid, &$data) {
		if (strlen($data) > 6) {
			$startCode = $this->unpackInt(false, $data);
			if (($startCode & 0xffffff00) === 0x00000100) {
				$streamId = $startCode & 0xff;
				$pos = 6;
				if (!in_array($streamId, self::HEADERLESS_STREAM_IDS)) {
					$headerDataLength = $this->unpackByte($data, $pos + 2);
					$pos = $pos + 3 + $headerDataLength;
				}

				return $this->handleStream($pid, $this->pidStat[$pid], $data, $pos);
			}
		}

		return true;
	}

	protected function handleStream($pid, $streamType, &$data, $pos) {
		return true;
	}

	static function crc32(&$str, $len) {
		$crc = 0xffffffff;

		for ($i = 0; $i < $len; $i++) {
			$byte = ord($str[$i]);
			$crc ^= $byte << 24;

			for ($j = 0; $j < 8; $j++) {
				$crc = ($crc & 0x80000000) ? (($crc << 1) & 0xffffffff) ^ 0x04c11db7 : ($crc << 1) & 0xffffffff;
			}
		}

		return $crc & 0xffffffff;
	}
}
