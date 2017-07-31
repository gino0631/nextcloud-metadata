<?php
namespace OCA\Metadata\Controller;

use OC\Files\Filesystem;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MetadataController extends Controller {
    const METERING_MODES = array(
        0 => 'Unknown',
        1 => 'Average',
        2 => 'Center Weighted Average',
        3 => 'Spot',
        4 => 'Multi Spot',
        5 => 'Pattern',
        6 => 'Partial',
        255 => 'Other'
    );

    protected $language;

    public function __construct($appName, IRequest $request) {
        parent::__construct($appName, $request);
        $this->language = \OC::$server->getL10N('metadata');
    }

    public function get($source) {
        $file = Filesystem::getLocalFile($source);
        if (!$file) {
            return new JSONResponse(
                array(
                    'response' => 'error',
                    'msg' => $this->language->t('File not found.')
                )
            );
        }

        $mimetype = Filesystem::getMimeType($source);
        switch ($mimetype) {
            case 'image/jpeg':
            case 'image/tiff':
                $metadata = $this->readExif($file, $lat, $lon);
                break;

            default:
                return new JSONResponse(
                    array(
                        'response' => 'error',
                        'msg' => $this->language->t('Unsupported MIME type "%s".', array($mimetype))
                    )
                );
        }

        if (!empty($metadata)) {
            return new JSONResponse(
                array(
                    'response' => 'success',
                    'metadata' => $metadata,
                    'lat' => $lat,
                    'lon' => $lon
                )
            );

        } else {
            return new JSONResponse(
                array(
                    'response' => 'error',
                    'msg' => $this->language->t('No metadata found.')
                )
            );
        }
    }

    protected function readExif($file, &$lat, &$lon) {
        $return = array();

        if ($sections = exif_read_data($file, 0, true)) {
            $comp = $this->getVal('COMPUTED', $sections, array());
            $ifd0 = $this->getVal('IFD0', $sections, array());
            $exif = $this->getVal('EXIF', $sections, array());
            $gps = $this->getVal('GPS', $sections, array());

//            $this->dump($sections, $return);

            if ($v = $this->getVal('DateTimeOriginal', $exif)) {
                $v[4] = $v[7] = '-';
                $this->addValT('Date taken', $v, $return);
            }

            if (($w = $this->getVal('ExifImageWidth', $exif)) && ($h = $this->getVal('ExifImageLength', $exif))) {
                if ($ornt = $this->getVal('Orientation', $ifd0)) {
                    if ($ornt >= 5) {
                      $tmp = $w;
                      $w = $h;
                      $h = $tmp;
                    }
                }

            } else {
                $w = $this->getVal('Width', $comp);
                $h = $this->getVal('Height', $comp);
            }

            if ($w && $h) {
                $this->addValT('Dimensions', $w . ' x ' . $h, $return);
            }

            if ($v = $this->getVal('Artist', $ifd0)) {
                $this->addValT('Artist', $v, $return);
            }

            if ($v = $this->getVal('Make', $ifd0)) {
                $this->addValT('Camera used', $v, $return);
            }

            if ($v = $this->getVal('Model', $ifd0)) {
                $this->addValT('Camera used', $v, $return);
            }

            if ($v = $this->getVal('Software', $ifd0)) {
                $this->addValT('Software', $v, $return);
            }

            if ($v = $this->getVal('ApertureFNumber', $comp)) {
                $this->addValT('F-stop', $v, $return);
            }

            if ($v = $this->getVal('ExposureTime', $exif)) {
                $this->addValT('Exposure time', $this->language->t('%s sec.', array($this->normRational($v))), $return);
            }

            if ($v = $this->getVal('ISOSpeedRatings', $exif)) {
                $this->addValT('ISO speed', $this->language->t('ISO-%s', array($v)), $return);
            }

            if ($v = $this->getVal('ExposureBiasValue', $exif)) {
                $this->addValT('Exposure bias', $this->language->t('%s step', array($this->evalRational($v))), $return);
            }

            if ($v = $this->getVal('FocalLength', $exif)) {
                $this->addValT('Focal length', $this->language->t('%g mm', array($this->evalRational($v))), $return);
            }

            if ($v = $this->getVal('MaxApertureValue', $exif)) {
                $this->addValT('Max aperture', $this->evalRational($v), $return);
            }

            if ($v = $this->getVal('MeteringMode', $exif)) {
                $this->addValT('Metering mode', $this->formatMeteringMode($v), $return);
            }

            if ($v = $this->getVal('Flash', $exif)) {
                $this->addValT('Flash mode', $this->formatFlashMode($v), $return);
            }

            if ($v = $this->getVal('FocalLengthIn35mmFilm', $exif)) {
                $this->addValT('35mm focal length', $v, $return);
            }

            if ($v = $this->getVal('GPSLatitude', $gps)) {
                $ref = $this->getVal('GPSLatitudeRef', $gps);
                $this->addValT('GPS latitude', $ref . ' ' . $this->formatGpsCoord($v), $return);
                $lat = $this->gpsToDecDegree($v, $ref == 'N');
            }

            if ($v = $this->getVal('GPSLongitude', $gps)) {
                $ref = $this->getVal('GPSLongitudeRef', $gps);
                $this->addValT('GPS longitude', $ref . ' ' . $this->formatGpsCoord($v), $return);
                $lon = $this->gpsToDecDegree($v, $ref == 'E');
            }
        }

        return $return;
    }

    protected function formatMeteringMode($mode) {
        if (!array_key_exists($mode, MetadataController::METERING_MODES)) {
            $mode = 255;
        }

        return $this->language->t(MetadataController::METERING_MODES[$mode]);
    }

    protected function formatFlashMode($mode) {
        if ($mode & 0x20) {
            return $this->language->t('No flash function');

        } else {
            $return = $this->language->t(($mode & 0x01) ? 'Flash' : 'No flash');

            if (($compuls = ($mode & 0x18)) != 0) {
                $return .= ', ' . $this->language->t(($compuls == 0x18) ? 'auto' : 'compulsory');
            }

            if ($mode & 0x40) {
                $return .= ', ' . $this->language->t('red-eye');
            }

            if (($strobe = ($mode & 0x06)) != 0) {
                $return .= ', ' . $this->language->t(($strobe == 0x06) ? 'strobe return' : (($strobe == 0x04) ? 'no strobe return' : ''));
            }

            return $return;
        }
    }

    protected function formatGpsCoord($coord) {
        $return = $this->evalRational($coord[0]) . '°';

        if (($coord[1] != '0/1') || ($coord[2] != '0/1')) {
            $return .= ' ' . $this->evalRational($coord[1]) . '\'';
        }

        if ($coord[2] != '0/1') {
            $return .= ' ' . $this->evalRational($coord[2]) . '"';
        }

        return $return;
    }

    protected function gpsToDecDegree($coord, $pos) {
        $return = round($this->evalRational($coord[0]) + ($this->evalRational($coord[1]) / 60) + ($this->evalRational($coord[2]) / 3600), 8);

        return $pos? $return : -$return;
    }

    protected function evalRational($val) {
        if (preg_match('/(\d+)([\/])(\d+)/', $val, $matches) !== FALSE) {
            $val = $matches[1] / $matches[3];
        }

        return $val;
    }

    protected function normRational($val) {
        if (preg_match('/(\d+)([\/])(\d+)/', $val, $matches) !== FALSE) {
            if ($matches[1] != 1) {
                $val = 1 . '/' . round($matches[3] / $matches[1]);
            }
        }

        return $val;
    }

    protected function getVal($key, &$array, $default = null) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        return $default;
    }

    protected function addVal($key, $val, &$array) {
        if (array_key_exists($key, $array)) {
            $val = $array[$key] . ' ' . $val;
        }

        $array[$key] = $val;
    }

    protected function addValT($key, $val, &$array) {
        $this->addVal($this->language->t($key), $val, $array);
    }

    protected function dump(&$sections, &$array) {
        foreach ($sections as $section => $data) {
            foreach ($data as $key => $val) {
                $this->addVal($section . '.' . utf8_encode($key), utf8_encode($val), $array);
            }
        }
    }
}
