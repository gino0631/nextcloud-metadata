<?php
namespace OCA\Metadata\Service;

class BmffParser extends FileReader {
	public function parseBmff($hnd, $size = 0) {
		$current = ftell($hnd);
		$limit = $current + $size;
		$continue = true;

		while (!feof($hnd) && ($continue !== false)) {
			if (($size > 0) && ($current >= $limit)) {
				return;
			}

			$continue = $this->readBox($hnd, $current);
		}
	}

	private function readBox($hnd, &$current) {
		if (($boxSize = self::readInt($hnd, false)) === false) return false;
		if (($boxType = self::readString($hnd, 4)) === false) return false;

		if ($boxSize === 1) {
			if (($boxSize = self::readLong($hnd, false)) === false) return false;
		}

		$ret = $this->processBox($hnd, $boxType, $boxSize);

		if ($boxSize > 1) {
			$current += $boxSize;
			fseek($hnd, $current);

		} else {
			fseek($hnd, 0, SEEK_END);
			$current = ftell($hnd);
		}

		return $ret;
	}

	private function continueFullBox($hnd, &$version) {
		$version = self::readByte($hnd);
		self::readByte($hnd);
		self::readShort($hnd, false);
	}

	protected function processBox($hnd, $boxType, $boxSize) {
		switch ($boxType) {
			case 'meta':
				$this->continueFullBox($hnd, $version);
				$this->parseBmff($hnd, $boxSize);
				break;

			case 'iinf':
				$this->continueFullBox($hnd, $version);
				$entryCount = ($version === 0) ? self::readShort($hnd, false) : self::readInt($hnd, false);
				$current = ftell($hnd);
				for ($i = 0; $i < $entryCount; $i++) {
					$this->readBox($hnd, $current);
				}
				break;

			case 'infe':
				$this->continueFullBox($hnd, $version);
				if ($version >= 2) {
					$itemId = ($version === 2) ? self::readShort($hnd, false) : (($version === 3) ? self::readInt($hnd, false) : null);
					$itemProtIndex = self::readShort($hnd, false);
					$itemType = self::readString($hnd, 4);

					$this->processItemInfoBox($itemType, $itemId);
				}
				break;

			case 'iloc':
				$this->continueFullBox($hnd, $version);
				$offsetAndLengthSizes = self::readByte($hnd);
				$offsetSize = $offsetAndLengthSizes >> 4;
				$lengthSize = $offsetAndLengthSizes & 0xf;

				$baseOffsetAndIndexSizes = self::readByte($hnd);
				$baseOffsetSize = $baseOffsetAndIndexSizes >> 4;
				$indexSize = (($version === 1) || ($version === 2)) ? $baseOffsetAndIndexSizes & 0xf : 0;

				$itemCount = ($version < 2) ? self::readShort($hnd, false) : (($version === 2) ? self::readInt($hnd, false) : null);
				for ($i = 0; $i < $itemCount; $i++) {
					$itemId = ($version < 2) ? self::readShort($hnd, false) : self::readInt($hnd, false);

					if (($version === 1) || ($version === 2)) {
						self::readShort($hnd, false);
					}
					$dataReferenceIndex = self::readShort($hnd, false);
					$baseOffset = ($baseOffsetSize > 0) ? self::readN($hnd, $baseOffsetSize, false) : 0;
					$extentCount = self::readShort($hnd, false);
					for ($j = 0; $j < $extentCount; $j++) {
						if ($indexSize > 0) {
							fread($hnd, $indexSize);
						}
						$extentOffset = self::readN($hnd, $offsetSize, false);
						$extentLength = self::readN($hnd, $lengthSize, false);
        
						$this->processItemExtent($itemId, $baseOffset + $extentOffset, $extentLength);
					}
				}
				break;
		}
	}

	protected function processItemInfoBox($itemType, $itemId) {
	}

	protected function processItemExtent($itemId, $extentOffset, $extentLength) {
	}
}
