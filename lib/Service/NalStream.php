<?php
namespace OCA\Metadata\Service;

class NalStream {
	private const START_SEQ = "\x00\x00\x00\x01";
	private $data;
	private $pos;
	private $zeros = 0;
	private $bbyte = 0;
	private $bmask = 0;
	private $bpos = 0;

	public function __construct(&$data, $pos) {
		$this->data = $data;
		$this->pos = $pos;

		$this->nextUnit();
	}

	public function nextUnit() {
		$this->pos = strpos($this->data, self::START_SEQ, $this->pos);

		if ($this->hasUnit()) {
			$this->pos += strlen(self::START_SEQ);
		}
	}

	public function hasUnit() {
		return ($this->pos !== false);
	}

	public function readFf() {
		$num = 0;
		do {
			if (($b = $this->readByte()) === false) {
				return false;
			}
			$num += $b;
		} while ($b === 0xFF);

		return $num;
	}

	public function readUe() {
		$x = 1;
		$y = 0;
		$zeroBits = 0;

		while (true) {
			$b = $this->readBit();
			if ($b === false) {
				return false;
			}

			if ($b === 0) {
				$zeroBits++;
			} else {
				break;
			}
		}

		for ($i = 0; $i < $zeroBits; $i++) {
			if (($b = $this->readBit()) === false) {
				return false;
			}

			$x <<= 1;
			$y <<= 1;
			$y |= $b;
		}

		return $x - 1 + $y;
	}

	public function readSe() {
		$num = 0;

		if (($num = $this->readUe()) === false) {
			return false;
		}

		$num += 1;

		return ($num & 1) ? -($num >> 1) : ($num >> 1);
	}

	public function readBit() {
		if (($this->bmask === 0) || ($this->pos !== $this->bpos)) {
			if (($this->bbyte = $this->readByte()) === false) {
				return false;
			}

			$this->bmask = 0x80;
			$this->bpos = $this->pos;
		}

		$b = $this->bbyte & $this->bmask;
		$this->bmask >>= 1;

		return ($b === 0) ? 0 : 1;
	}

	public function readBytes($n) {
		$res = '';

		for ($i = 0; $i < $n; $i++) {
			if (($b = $this->readByte()) === false) {
				return false;
			}
			$res .= chr($b);
		}

		return $res;
	}

	public function readByte() {
		$b = ord($this->data[$this->pos]);

		if ($b === 0x00) {
			if (substr($this->data, $this->pos, 4) === self::START_SEQ) {
				return false;
			}

			$this->zeros++;
			$this->pos++;

		} else if (($b === 0x03) && ($this->zeros === 2)) {
			$this->zeros = 0;
			$this->pos++;
			return $this->readByte();

		} else {
			$this->zeros = 0;
			$this->pos++;
		}

		return $b;
	}
}
