<?php
namespace OCA\Metadata\Service;

class JpegMetadata extends FileReader {
    const APP1 = "\xE1";
    const APP13 = "\xED";

    private $ifd0 = array();
    private $xmp = array();
    private $iptc = array();
    private $gps = array();

    public static function fromFile($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $obj = new JpegMetadata();
                $obj->readJpeg($hnd);

                return $obj;

            } finally {
                fclose($hnd);
            }
        }

        return null;
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

    protected function readJpeg($hnd) {
        $data = fread($hnd, 2);

        if ($data === "\xFF\xD8") {     // SOI (Start Of Image)
            $data = fread($hnd, 2);

            // While not EOF, tag is valid, and not SOS (Start Of Scan) or EOI (End Of Image)
            while (!feof($hnd) && ($data[0] === "\xFF") && ($data[1] !== "\xDA") && ($data[1] !== "\xD9")) {
                if ((ord($data[1]) < 0xD0) || (ord($data[1]) > 0xD7)) {     // All segments but RSTn have size bytes
                    $size = self::readShort($hnd, false) - 2;

                    if ($size > 0) {
                        $pos = ftell($hnd);
                        $result = $this->processData($hnd, $data[1], $size);
                        if ($result) {
                            return $result;
                        }

                        fseek($hnd, $pos + $size);
                    }
                }

                $data = fread($hnd, 2);
            }
        }
    }

    protected function processData($hnd, $marker, $size) {
        switch ($marker) {
            case self::APP1:
                $start = ftell($hnd);
                if ($this->tryXmp($hnd, $size)) {
                    break;
                }
                fseek($hnd, $start);
                $this->tryExif($hnd, $size);
                break;

            case self::APP13:
                $iptcMetadata = IptcMetadata::fromData(fread($hnd, $size));
                $this->iptc = $iptcMetadata->getArray();
                break;
        }

        return null;
    }

    protected function tryXmp($hnd, $size) {
        if ($size > 29) {
            $data = fread($hnd, 29);
            $size -= 29;

            if ($data === 'http://ns.adobe.com/xap/1.0/' . "\x00") {
                $xmpMetadata = XmpMetadata::fromData(fread($hnd, $size));
                $this->xmp = $xmpMetadata->getArray();
                return true;
            }
        }

        return false;
    }

    protected function tryExif($hnd, $size) {
        if ($size > 14) {
            $data = fread($hnd, 6);

            if ($data === 'Exif'."\x00\x00") {
                $pos = ftell($hnd);
                $tiffMetadata = TiffMetadata::fromFileData($hnd, $pos);
                $this->ifd0 = $tiffMetadata->getIfd0();
                $this->gps = $tiffMetadata->getGps();
                return true;
            }
        }

        return false;
    }
}
