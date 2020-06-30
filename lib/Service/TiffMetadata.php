<?php
namespace OCA\Metadata\Service;

class TiffMetadata extends TiffParser {
    const RATING = 0x4746;
    const XMP = 0x02BC;
    const IPTC = 0x83BB;
    const GPS = 0x8825;

    private $ifd0 = array();
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

    public function getIfd0() {
        return $this->ifd0;
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
            case self::RATING:
                $this->ifd0['Rating'] = $offsetOrData;
                break;

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
                $gpsParser = new class() extends TiffParser {
                    public $gps = array();

                    protected function processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData) {
                        switch ($tagId) {
                            case 0x01:
                                $this->gps['GPSLatitudeRef'] = $offsetOrData;
                                break;
                            case 0x02:
                                fseek($hnd, $pos + $offsetOrData);
                                $this->gps['GPSLatitude'] = array(self::readRat($hnd, $intel), self::readRat($hnd, $intel), self::readRat($hnd, $intel));
                                break;
                            case 0x03:
                                $this->gps['GPSLongitudeRef'] = $offsetOrData;
                                break;
                            case 0x04:
                                fseek($hnd, $pos + $offsetOrData);
                                $this->gps['GPSLongitude'] = array(self::readRat($hnd, $intel), self::readRat($hnd, $intel), self::readRat($hnd, $intel));
                                break;
                            case 0x05:
                                $this->gps['GPSAltitudeRef'] = $offsetOrData;
                                break;
                            case 0x06:
                                fseek($hnd, $pos + $offsetOrData);
                                $this->gps['GPSAltitude'] = self::readRat($hnd, $intel);
                                break;
                        }
                    }
                };
                $gpsParser->parseTiffIfd($hnd, $pos, $intel, $offsetOrData);
$this->gps=$gpsParser->gps;
                break;
        }

        return null;
    }
}
