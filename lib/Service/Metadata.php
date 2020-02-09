<?php
namespace OCA\Metadata\Service;

class Metadata {
	protected $array;
	protected $lat;
	protected $lon;
	protected $loc;

	public function __construct($array, $lat = null, $lon = null, $loc = null) {
		$this->array = $array;
		$this->lat = $lat;
		$this->lon = $lon;
		$this->loc = $loc;
	}

	public function getArray() {
		return $this->array;
	}

	public function getLat() {
		return $this->lat;
	}

	public function getLon() {
		return $this->lon;
	}

	public function getLoc() {
		return $this->loc;
	}

	public function isEmpty() {
		return empty($this->array);
	}

	public function addVal($key, $val, $join = null, $sep = null) {
		if (is_array($val)) {
			if (isset($join)) {
				$val = join($join, $val);

			} else if (count($val) <= 1) {
				$val = array_pop($val);
			}
		}

		if (array_key_exists($key, $this->array)) {
			$prev = $this->array[$key];

			if (isset($sep)) {
				if (substr($val, 0, strlen($prev)) !== $prev) {
					$val = $prev . $sep . $val;
				}

			} else {
				if (!is_array($prev)) {
					$prev = array($prev);
				}

				if (is_array($val)) {
					$val = array_merge($prev, $val);

				} else {
					$prev[] = $val;
					$val = $prev;
				}
			}
		}

		$this->array[$key] = $val;
	}

	public function dump(&$data, $prefix = '') {
		foreach ($data as $key => $val) {
			if (is_array($val)) {
				$this->dump($val, $prefix . $key . '.');

			} else {
				$this->addVal($prefix . utf8_encode($key), utf8_encode($val));
			}
		}
	}
}
