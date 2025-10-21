<?php
namespace OCA\Metadata\Service;

use OCA\Metadata\GetID3\getID3;

class MtsMetadata extends MtsParser {
	private const CAMERA_MAKERS = array (
		0x0103 => 'Panasonic',
		0x0108 => 'Sony',
		0x1011 => 'Canon',
		0x1104 => 'JVC'
	);
	private const CAMERA_MODELS = array (
		0x0103 => array (	// Panasonic
			0x0300 => 'HDC-SD1',
			0x0311 => 'HDC-SD9',
			0x0315 => 'HDC-TM300',
			0x0330 => 'HDC-SD700',
			0x0331 => 'HDC-TM700',
			0x0333 => 'HDC-SD60',
			0x0335 => 'HDC-HS60',
			0x0336 => 'HDC-SDT750',
			0x0411 => 'AG-HMC41',
			0x0822 => 'DMC-GH4'
		),
		0x1011 => array (	// Canon
			0x2001 => 'HG20',
			0x3002 => 'HF200',
			0x3003 => 'HF S100',
			0x3100 => 'HF R16',
			0x3101 => 'HF M31',
			0x3102 => 'HF S20'
		),
		0x1104 => array (	// JVC
			0x8421 => 'GZ-HM550',
			0x8621 => 'GZ-HM1',
			0x8811 => 'GZ-X900'
		)
	);

	private $sections = array();
	private $mdpmParsed = false;
	private $spsParsed = false;

	public static function fromFile($hnd) {
		$obj = new MtsMetadata();
		$obj->parseMts($hnd, 8192);

		return $obj->sections;
	}

	protected function handleStream($pid, $streamType, &$data, $pos) {
		switch ($streamType) {
			case MtsParser::STREAM_TYPE_VIDEO_MPEG1:
				$this->getId3($data, $pos, 'video');
				$this->sections['video']['codec'] = 'MPEG-1';
				return false;

			case MtsParser::STREAM_TYPE_VIDEO_MPEG2:
				$this->getId3($data, $pos, 'video');
				$this->sections['video']['codec'] = 'MPEG-2';
				return false;

			case MtsParser::STREAM_TYPE_AUDIO_MPEG1:
				$this->getId3($data, $pos, 'audio');
				$this->sections['audio']['codec'] = 'MPEG-1';
				return false;

			case MtsParser::STREAM_TYPE_AUDIO_MPEG2:
				$this->getId3($data, $pos, 'audio');
				$this->sections['audio']['codec'] = 'MPEG-2';
				return false;

			case MtsParser::STREAM_TYPE_AUDIO_AC3:
				$this->getId3($data, $pos, 'audio');
				$this->sections['audio']['codec'] = 'AC-3';
				return false;

			case MtsParser::STREAM_TYPE_VIDEO_H264:
				return $this->parseH264($data, $pos);

			default:
				return false;
		}
	}


	private function getId3(&$data, $pos, $section) {
		$tmp = fopen('php://temp', 'r+');
		fwrite($tmp, substr($data, $pos));
		rewind($tmp);

		$getId3 = new getID3();
		$getId3->option_save_attachments = false;
		$sections = $getId3->analyze('', strlen($data) - $pos, '', $tmp);	// closes $tmp
		$this->sections[$section] = $sections[$section];
	}
	
	private function parseH264(&$data, $pos) {
		for ($nalStream = new NalStream($data, $pos); $nalStream->hasUnit(); $nalStream->nextUnit()) {
			$nalUnitType = $nalStream->readByte();
			if (($nalUnitType === false) || ($nalUnitType & 0x80)) {	// must be 0
				return false;
			}

			$nalUnitType = $nalUnitType & 0x1F;
			switch ($nalUnitType) {
				case 0x06:	// SEI
					if (!$this->mdpmParsed) {
						$payloadType = $nalStream->readFf();
						$payloadSize = $nalStream->readFf();

						if ($payloadType === 0x05) {	// User data unregistered
							$id = $nalStream->readBytes(20);

							if ($id === "\x17\xee\x8c\x60\xf8\x4d\x11\xd9\x8c\xd6\x08\0\x20\x0c\x9a\x66MDPM") {	// MDPM
								$cnt = $nalStream->readByte();
								$dt = '';
								$maker = '';
								$model = '';

								for ($i = 0; $i < $cnt; $i++) {
									$tag = $nalStream->readByte();
									$buf = $nalStream->readBytes(4);

									switch ($tag) {
										case 0x18:
											$dt = $buf;
											break;
										case 0x19:
											$dt .= $buf;
											break;

										case 0xE0:
											$maker = $buf;
											break;
										case 0xE4:
											$model = $buf;
											break;
										case 0xE5:
										case 0xE6:
											$model .= $buf;
											break;
									}
								}

								$this->decodeCreationDate($dt);
								$this->decodeCamera($maker, $model);
								$this->mdpmParsed = true;
							}
						}
					}
					break;

				case 0x07:	// SPS
					if (!$this->spsParsed) {
						$profileIdc = $nalStream->readByte();
						$flags = $nalStream->readByte();
						$levelIdc = $nalStream->readByte();
						$spsId = $nalStream->readUe();
						if (in_array($profileIdc, array(100, 110, 122, 244, 44, 83, 86, 118, 128))) {
							$chromaFormatIdc = $nalStream->readUe();
							if ($chromaFormatIdc === 3) {
								$nalStream->readBit();
							}
							$nalStream->readUe();	// bit_depth_luma_minus8
							$nalStream->readUe();	// bit_depth_chroma_minus8
							$nalStream->readBit();
							if ($nalStream->readBit() === 1) {	// seq_scaling_matrix_present_flag
								for ($i = 0; $i < (($chromaFormatIdc !== 3) ? 8 : 12); $i++) {
									if ($nalStream->readBit() === 1) {	// seq_scaling_list_present_flag
										$size = ($i < 6) ? 16 : 64;
										$lastScale = 8;
										$nextScale = 8;
										for ($j = 0; $j < $size; $j++) {
											if ($nextScale !== 0) {
												$nextScale = ($lastScale + $nalStream->readSe() + 256) % 256;
											}
											$lastScale = ($nextScale === 0) ? $lastScale : $nextScale;
										}
									}
								}
							}
						}
						$nalStream->readUe();		// log2_max_frame_num_minus4
						$cntType = $nalStream->readUe();	// pic_order_cnt_type
						if ($cntType === 0) {
							$nalStream->readUe();	// log2_max_pic_order_cnt_lsb_minus4
						} else if ($cntType === 1) {
							$nalStream->readBit();	// delta_pic_order_always_zero_flag
							$nalStream->readSe();	// offset_for_non_ref_pic
							$nalStream->readSe();	// offset_for_top_to_bottom_field
							$num = $nalStream->readUe();	// num_ref_frames_in_pic_order_cnt_cycle
							for ($i = 0; $i < $num; $i++) {
								$nalStream->readSe();	// offset_for_ref_frame
							}
						}
						$nalStream->readUe();		// max_num_ref_frames
						$nalStream->readBit();		// gaps_in_frame_num_value_allowed_flag
						$w = $nalStream->readUe();	// pic_width_in_mbs_minus1
						$h = $nalStream->readUe();	// pic_height_in_map_units_minus1
						$f = $nalStream->readBit();	// frame_mbs_only_flag
						if ($f === 0) {	
							$nalStream->readBit();	// mb_adaptive_frame_field_flag
						}
						$nalStream->readBit();		// direct_8x8_inference_flag
						$w = ($w + 1) * 16;
						$h = (2 - $f) * ($h + 1) * 16;
						if ($nalStream->readBit() === 1) {	// frame_cropping_flag
							$l = $nalStream->readUe();	// frame_crop_left_offset
							$r = $nalStream->readUe();	// frame_crop_right_offset
							$t = $nalStream->readUe();	// frame_crop_top_offset
							$b = $nalStream->readUe();	// frame_crop_bottom_offset
							$m = 4 - $f * 2;
							$w = $w - ($l * 4) - ($r * 4);
							$h = $h - ($t * $m) - ($b * $m);
						}

						$this->sections['video']['resolution_x'] = $w;
						$this->sections['video']['resolution_y'] = $h;
						$this->sections['video']['fourcc'] = 'avc1';
						$this->spsParsed = true;
					}
					break;
			}
		}

		return !$this->mdpmParsed || !$this->spsParsed;
	}

	private function decodeCreationDate(&$dt) {
		if (strlen($dt) === 8) {
			$dt = unpack('C*', $dt);
			$this->sections['video']['creation_date'] = sprintf('%02x%02x-%02x-%02x %02x:%02x:%02x %s%02d:%s%s',
				$dt[2], $dt[3], $dt[4], $dt[5], $dt[6], $dt[7], $dt[8],
				($dt[1] & 0x20) ? '-' : '+', ($dt[1] >> 1) & 0xF, ($dt[1] & 0x01) ? '30' : '00', ($dt[1] & 0x40) ? ' DST' : '');
		}
	}

	private function decodeCamera(&$makerBuf, &$modelBuf) {
		if (strlen($makerBuf) === 4) {
			$maker = $this->unpackShort(false, $makerBuf);
			$this->sections['video']['camera'] = $this->decode($maker, self::CAMERA_MAKERS);

			$model = rtrim($modelBuf);
			if ((strlen($model) > 0) && (ctype_print($model))) {
				$this->sections['video']['camera'] .= ' ' . $model;

			} else {
				if (array_key_exists($maker, self::CAMERA_MODELS)) {
					$model = $this->unpackShort(false, $makerBuf, 2);
					$this->sections['video']['camera'] .= ' ' . $this->decode($model, self::CAMERA_MODELS[$maker]);
				}
			}
		}
	}

	private function decode($key, $table) {
		return array_key_exists($key, $table) ? $table[$key] : sprintf('? (0x%04X)', $key);
	}
}
