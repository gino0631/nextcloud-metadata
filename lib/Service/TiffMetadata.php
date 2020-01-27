<?php
namespace OCA\Metadata\Service;

class TiffMetadata extends TiffParser {
    const XMP = 0x02BC;
    const IPTC = 0x83BB;
    const GPS = 0x8825;

    private $xmp = array();
    private $iptc = array();
    private $gps = array();

    public static function fromFile($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $obj = new TiffMetadata();
                $obj->parseTiff($hnd, 0);

                return $obj;

            } finally {
                fclose($hnd);
            }
        }

        return null;
    }

    public static function fromFileData($hnd, $pos) {
        $obj = new TiffMetadata();
        $obj->parseTiff($hnd, $pos);

        return $obj;
    }

    public function getXmp() {
        return $this->xmp;
    }

    public function getIptc() {
        return $this->iptc;
    }

    public function getGps() {
        return $this->gps;
    }

    protected function processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData) {
        switch ($tagId) {
            case self::XMP:
                fseek($hnd, $offsetOrData);           // Go to XMP
                $xmpMetadata = XmpMetadata::fromData(fread($hnd, $size));
                $this->xmp = $xmpMetadata->getArray();
                break;

            case self::IPTC:
                fseek($hnd, $offsetOrData);           // Go to IPTC
                $iptcMetadata = IptcMetadata::fromData(fread($hnd, $size));
                $this->iptc = $iptcMetadata->getArray();
                break;

            case self::GPS:
                $gpsParser = new class($this->gps) extends TiffParser {
                    protected $gps;
                    public function __construct($gps) {
                        $this->gps = $gps;
                    }

                    protected function processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData) {
                        switch ($tagId) {
                            case 0x01:
                                $gps['GPSLatitudeRef'] = $offsetOrData;
                                break;
                            case 0x02:
                                fseek($hnd, $pos + $offsetOrData);
                                $gps['GPSLatitude'] = array($this->readRat($hnd, $intel), $this->readRat($hnd, $intel), $this->readRat($hnd, $intel));
                                break;
                            case 0x03:
                                $gps['GPSLongitudeRef'] = $offsetOrData;
                                break;
                            case 0x04:
                                fseek($hnd, $pos + $offsetOrData);
                                $gps['GPSLongitude'] = array($this->readRat($hnd, $intel), $this->readRat($hnd, $intel), $this->readRat($hnd, $intel));
                                break;
                            case 0x05:
                                $gps['GPSAltitudeRef'] = $offsetOrData;
                                break;
                            case 0x06:
                                fseek($hnd, $pos + $offsetOrData);
                                $gps['GPSAltitude'] = $this->readRat($hnd, $intel);
                                break;
                        }
                    }
                };
                $gpsParser->parseTiffIfd($hnd, $pos, $intel, $offsetOrData);
                break;
        }

        return null;
    }
}
