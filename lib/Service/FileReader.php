<?php
namespace OCA\Metadata\Service;

class FileReader {
	public static function readByte($hnd) {
		return (($data = fread($hnd, 1)) !== false) ? self::unpackByte($data) : false;
	}

	public static function readShort($hnd, $intel) {
		return ((($data = fread($hnd, 2)) !== false) && (strlen($data) === 2)) ? self::unpackShort($intel, $data) : false;
	}

	public static function readInt($hnd, $intel) {
		return ((($data = fread($hnd, 4)) !== false) && (strlen($data) === 4)) ? self::unpackInt($intel, $data) : false;
	}

	public static function readLong($hnd, $intel) {
		return ((($data = fread($hnd, 8)) !== false) && (strlen($data) === 8)) ? self::unpackLong($intel, $data) : false;
	}

	public static function readN($hnd, $n, $intel) {
		return ((($data = fread($hnd, $n)) !== false) && (strlen($data) === $n)) ? self::unpackN($intel, $n, $data) : false;
	}

	public static function readRat($hnd, $intel) {
		return (($a = self::readInt($hnd, $intel)) && ($b = self::readInt($hnd, $intel))) ? ($a . '/' . $b) : false;
	}

	public static function readString($hnd, $len) {
		return fread($hnd, 4);
	}

	protected static function unpackByte($data) {
		return unpack(('C').'d', $data)['d'];
	}

	protected static function unpackShort($intel, $data) {
		return unpack(($intel? 'v' : 'n').'d', $data)['d'];
	}

	protected static function unpackInt($intel, $data) {
		return unpack(($intel? 'V' : 'N').'d', $data)['d'];
	}

	protected static function unpackLong($intel, $data) {
		return unpack(($intel? 'P' : 'J').'d', $data)['d'];
	}

	protected static function unpackN($intel, $n, $data) {
		switch($n) {
			case 1:
				return self::unpackByte($data);

			case 2:
				return self::unpackShort($intel, $data);

			case 4:
				return self::unpackInt($intel, $data);

			case 8:
				return self::unpackLong($intel, $data);

			default:
				throw new \Exception('Unsupported size ' . $n);
		}
	}
}
