<?php
namespace OCA\Metadata\Services;

class Metadata {
	public function __construct($metadataArray, $lat, $lon) {
		$this->metadataArray = $metadataArray;
		$this->lat = $lat;
		$this->lon = $lon;
	}

	public $metadataArray;
	public $lat;
	public $lon;
}
