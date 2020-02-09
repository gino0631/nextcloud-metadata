<?php
namespace OCA\Metadata\Service;

class HeicMetadata extends BmffParser {
	const EXIF_HEADER_SIZE = 10;

	private $exifItemId;
	private $exifOffset;
	private $exifLength;
	private $exif = array();
	private $itemInfosSeen;
	private $itemExtents = array();

	public static function fromFile($file) {
		if ($hnd = fopen($file, 'rb')) {
			try {
				$obj = new HeicMetadata();
				$obj->readHeic($hnd);

				return ($obj->exif) ? $obj : null;

			} finally {
				fclose($hnd);
			}
		}

		return null;
	}

	public function getExif() {
		return $this->exif;
	}

	private function readHeic($hnd) {
		$this->parseBmff($hnd);

		if ($this->exifOffset) {
			if ($stream = fopen('php://memory','rb+')) {
				try {
					fseek($hnd, $this->exifOffset + self::EXIF_HEADER_SIZE);
					stream_copy_to_stream($hnd, $stream, $this->exifLength - self::EXIF_HEADER_SIZE);
					$this->exif = exif_read_data($stream, 0, true);

				} finally {
					fclose($stream);
				}
			}
		}
	}

	protected function processBox($hnd, $boxType, $boxSize) {
		$ret = parent::processBox($hnd, $boxType, $boxSize);

		if ($this->exifOffset) {
			$ret = false;
		}

		return $ret;
	}

	protected function processItemInfoBox($itemType, $itemId) {
		$this->itemInfosSeen = true;

		if ($itemType === 'Exif') {
			$this->exifItemId = $itemId;

			if (array_key_exists($itemId, $this->itemExtents)) {
				$extent = $this->itemExtents[$itemId];
				$this->exifOffset = $extent[0];
				$this->exifLength = $extent[1];
			}
		}
	}

	protected function processItemExtent($itemId, $extentOffset, $extentLength) {
		if ($this->itemInfosSeen) {
			if ($itemId === $this->exifItemId) {
				$this->exifOffset = $extentOffset;
				$this->exifLength = $extentLength;
			}

		} else {
			$this->itemExtents[$itemId] = array($extentOffset, $extentLength);
		}
	}
}
