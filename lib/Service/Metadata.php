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
}
