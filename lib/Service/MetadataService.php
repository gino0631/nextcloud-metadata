<?php
namespace OCA\Metadata\Service;

use OC\Files\Filesystem;
use OCA\Metadata\AppInfo\Application;
use OCA\Metadata\GetID3\getID3;

class MetadataService {
    const EMSP = "\xe2\x80\x83";
    const BLACK_STAR = "\xE2\x98\x85";
    const WHITE_STAR = "\xE2\x98\x86";

    protected $language;

    public function __construct() {
        $this->language = \OC::$server->getL10N(Application::APP_NAME);
    }

    /**
     * @NoAdminRequired
     * @throws \Exception
     */
    public function getMetadata($source) {
        \OC_Util::setupFS();
        $file = Filesystem::getLocalFile($source);
        if (!$file) {
            throw new \Exception($this->t('File not found.'));
        }

        $metadata = null;

        $mimetype = Filesystem::getMimeType($source);
        switch ($mimetype) {
            case 'audio/flac':
            case 'audio/mp4':
            case 'audio/mpeg':
            case 'audio/ogg':
            case 'audio/wav':
            case 'video/3gpp':
            case 'video/dvd':
            case 'video/mp4':
            case 'video/mpeg':
            case 'video/quicktime':
            case 'video/webm':
            case 'video/x-flv':
            case 'video/x-matroska':
            case 'video/x-msvideo':
                if ($sections = $this->readId3($file)) {
                    $metadata = $this->getAvMetadata($sections);
//                        $metadata->dump($sections);
                }
                break;

            case 'image/gif':
                if ($sections = $this->readGif($file)) {
                    $metadata = $this->getImageMetadata($sections);
//                        $this->dump($sections, $metadata);
                }
                break;

            case 'image/png':
                if ($sections = $this->readPng($file)) {
                    $metadata = $this->getImageMetadata($sections);
//                        $this->dump($sections, $metadata);
                }
                break;

            case 'image/heic':
                if ($heicMetadata = HeicMetadata::fromFile($file)) {
                    $sections = $heicMetadata->getExif();
                    $metadata = $this->getImageMetadata($sections);
//                    $metadata->dump($sections);
                }
                break;

            case 'image/jpeg':
                if ($sections = $this->readExif($file)) {
                    if ($jpegMetadata = JpegMetadata::fromFile($file)) {
                        $sections['XMP'] = array_merge($jpegMetadata->getIptc(), $jpegMetadata->getXmp());
                        $sections['IFD0'] = array_merge((array)$this->getVal('IFD0', $sections), $jpegMetadata->getIfd0());
                        if (!array_key_exists('GPS', $sections)) {
                            $sections['GPS'] = $jpegMetadata->getGps();
                        }
                    }
                    $metadata = $this->getImageMetadata($sections);
//                    $metadata->dump($sections);
                }
                break;

            case 'image/tiff':
                if ($sections = $this->readExif($file)) {
                    if ($tiffMetadata = TiffMetadata::fromFile($file)) {
                        $sections['XMP'] = array_merge($tiffMetadata->getIptc(), $tiffMetadata->getXmp());
                        $sections['IFD0'] = array_merge((array)$this->getVal('IFD0', $sections), $tiffMetadata->getIfd0());
                    }
                    $metadata = $this->getImageMetadata($sections);
//                    $metadata->dump($sections);
                }
                break;

            case 'image/x-dcraw':
                if ($sections = $this->readExif($file)) {
                    $sidecar = $file . '.xmp';
                    if (file_exists($sidecar)) {
                        if ($xmpMetadata = XmpMetadata::fromFile($sidecar)) {
                            $sections['XMP'] = $xmpMetadata->getArray();
                        }
                    }

                    $metadata = $this->getImageMetadata($sections);
//                        $this->dump($sections, $metadata);
                }
                break;

            case 'application/zip':
                if ($sections = $this->readZip($file)) {
                    $metadata = $this->getArchiveMetadata($sections);
                }
                break;

            default:
                throw new \Exception($this->t('Unsupported MIME type "%s".', array($mimetype)));
        }

        return $metadata;
    }

    protected function readId3($file) {
        $getId3 = new getID3();
        $getId3->option_save_attachments = false;

        return $getId3->analyze($file);
    }

    protected function readGif($file) {
        $computed = array();
        $this->getImageSize($file, $computed);

        return array(
            'COMPUTED' => $computed
        );
    }

    protected function readPng($file) {
        $computed = array();
        $this->getImageSize($file, $computed);

        return array(
            'COMPUTED' => $computed
        );
    }

    protected function getImageSize($file, &$return) {
        $size = getimagesize($file);
        $return['Width'] = $size[0];
        $return['Height'] = $size[1];

        return $return;
    }

    protected function readExif($file) {
        if (!function_exists('exif_read_data')) {
            throw new \Exception($this->t('EXIF support is missing; you might need to install an appropriate package for your system.'));
        }

        return @exif_read_data($file, 0, true);
    }

    protected function readZip($file) {
        $computed = array();

        $zip = new \ZipArchive();
        if ($zip->open($file) === true) {
            $computed['numFiles'] = $zip->numFiles;
            $computed['comment'] = $zip->comment;
            $zip->close();
        }

        return array(
            'COMPUTED' => $computed
        );
    }

    protected function getAvMetadata($sections) {
        $return = array();
        $lat = null;
        $lon = null;

        $audio = $this->getVal('audio', $sections) ?: array();
        $video = $this->getVal('video', $sections) ?: array();
        $tags = $this->getVal('tags', $sections) ?: array();
        $vorbis = $this->getVal('vorbiscomment', $tags) ?: array();
        $id3v2 = $this->getVal('id3v2', $tags) ?: array();
        $id3v1 = $this->getVal('id3v1', $tags) ?: array();
        $riff = $this->getVal('riff', $tags) ?: array();
        $quicktime = $this->getVal('quicktime', $tags) ?: array();
        $matroska = $this->getVal('matroska', $tags) ?: array();

        krsort($tags);  // make a predictable order with 'id3v2' before 'id3v1'

        if ($v = $this->getValM('title', $tags)) {
            $this->addVal($this->t('Title'), $v, $return);
        }

        if ($v = $this->getValM('artist', $tags)) {
            $this->addVal($this->t('Artist'), $v, $return);
        }

        if ($v = $this->getVal('playtime_seconds', $sections)) {
            $this->addVal($this->t('Length'), $this->formatSeconds($v), $return);
        }

        if (($x = $this->getVal('resolution_x', $video)) && ($y = $this->getVal('resolution_y', $video))) {
            $this->addVal($this->t('Dimensions'), $x . ' x ' . $y, $return);
        }

        if ($v = $this->getVal('frame_rate', $video)) {
            $this->addVal($this->t('Frame rate'), $this->t('%g fps', array($v)), $return);
        }

        if ($v = $this->getVal('bitrate', $sections)) {
            $this->addVal($this->t('Bit rate'), $this->t('%s kbps', array(floor($v/1000))), $return);
        }

        if ($v = $this->getVal('author', $quicktime)) {
            $this->addVal($this->t('Author'), $v, $return);
        }

        if ($v = $this->getVal('copyright', $quicktime)) {
            $this->addVal($this->t('Copyright'), $v, $return);
        }

        if ($v = $this->getVal('make', $quicktime)) {
            $this->addVal($this->t('Camera used'), $v, $return);
        }

        if ($v = $this->getVal('model', $quicktime)) {
            $this->addVal($this->t('Camera used'), $v, $return, null, ' ');
        }

        if ($v = $this->getVal('com.android.version', $quicktime)) {
            $this->addVal($this->t('Android version'), $v, $return);
        }

        if ($v = $this->getVal('codec', $video)) {
            $this->addVal($this->t('Video codec'), $v, $return);

        } else if ($v = $this->getVal('fourcc', $video)) {
            $this->addVal($this->t('Video codec'), $this->formatFourCc($v), $return);
        }

        if ($v = $this->getVal('bits_per_sample', $video)) {
            $this->addVal($this->t('Video sample size'), $this->t('%s bit', array($v)), $return);
        }

        if ($v = $this->getVal('codec', $audio)) {
            $this->addVal($this->t('Audio codec'), $v, $return);
        }

        if ($v = $this->getVal('channels', $audio)) {
            $this->addVal($this->t('Audio channels'), $v, $return);
        }

        if ($v = $this->getVal('sample_rate', $audio)) {
            $this->addVal($this->t('Audio sample rate'), $this->t('%s kHz', array($v/1000)), $return);
        }

        if ($v = $this->getVal('bits_per_sample', $audio)) {
            $this->addVal($this->t('Audio sample size'), $this->t('%s bit', array($v)), $return);
        }

        if ($v = $this->getValM('album', $tags) ?: $this->getVal('product', $riff)) {
            $this->addVal($this->t('Album'), $v, $return);
        }

        if ($v = $this->getVal('tracknumber', $vorbis) ?: $this->getVal('part', $riff) ?: $this->getVal('track_number', $id3v2) ?: $this->getVal('track', $id3v1)) {
            $this->addVal($this->t('Track #'), $v, $return);
        }

        if ($v = $this->getVal('date', $vorbis) ?: $this->getVal('creationdate', $riff) ?: $this->getVal('creation_date', $quicktime) ?: $this->getVal('year', $vorbis, $id3v2, $id3v1)) {
            $isYear = is_array($v) && (count($v) === 1) && (strlen($v[0]) === 4);
            $this->addVal($isYear ? $this->t('Year') : $this->t('Date'), $v, $return);
        }

        if ($v = $this->getValM('genre', $tags)) {
            $this->addVal($this->t('Genre'), $v, $return);
        }

        if ($v = $this->getVal('description', $vorbis) ?: $this->getValM('comment', $tags)) {
            if (is_array($v)) {
                $this->formatComments($v);
            }

            $this->addVal($this->t('Comment'), $v, $return);
        }

        if ($v = $this->getValM('encoded_by', $tags)) {
            $this->addVal($this->t('Encoded by'), $v, $return);
        }

        if ($v = $this->getVal('writingapp', $matroska) ?: $this->getVal('encoding_tool', $quicktime) ?: $this->getVal('software', $riff) ?: $this->getVal('encoder', $audio)) {
            $this->addVal($this->t('Encoding tool'), $v, $return);
        }

        if ($v = $this->getVal('gps_latitude', $quicktime)) {
            $lat = $v[0];
            $this->addVal($this->t('GPS coordinates'), $this->formatGpsDegree($lat, 'N', 'S'), $return);
        }

        if ($v = $this->getVal('gps_longitude', $quicktime)) {
            $lon = $v[0];
            $this->addVal($this->t('GPS coordinates'), $this->formatGpsDegree($lon, 'E', 'W'), $return, null, self::EMSP);
        }

        return new Metadata($return, $lat, $lon);
    }

    protected function getImageMetadata($sections) {
        $return = array();
        $lat = null;
        $lon = null;
        $loc = null;

        $comp = $this->getVal('COMPUTED', $sections) ?: array();
        $ifd0 = $this->getVal('IFD0', $sections) ?: array();
        $exif = $this->getVal('EXIF', $sections) ?: array();
        $gps = $this->getVal('GPS', $sections) ?: array();
        $xmp = $this->getVal('XMP', $sections) ?: array();

        if ($v = $this->getVal('title', $xmp)) {
            $this->addVal($this->t('Title'), $v, $return);
        }

        if ($v = $this->getVal('headline', $xmp)) {
            $this->addVal($this->t('Headline'), $v, $return);
        }

        if (($v = $this->convertUcs2($this->getVal('Subject', $ifd0))) || ($v = $this->getVal('description', $xmp)) || ($v = $this->getVal('caption', $xmp))) {
            $this->addVal($this->t('Description'), $v, $return);
        }

        if ($v = $this->getVal('Rating', $ifd0)) {
            $this->addVal($this->t('Rating'), $this->formatRating($v), $return);
        }

        if ($v = $this->getVal('captionWriter', $xmp)) {
            $this->addVal($this->t('Description writer'), $v, $return);
        }

        if ($v = $this->getVal('people', $xmp)) {
            $this->addVal($this->t('People'), $v, $return);
        }

        if (($v = $this->getVal('tags', $xmp)) || ($v = $this->getVal('categories', $xmp))) {
            $this->addVal($this->t('Tags'), $v, $return);
        }

        if ($v = $this->getVal('subject', $xmp)) {
            $this->addVal($this->t('Keywords'), $v, $return);
        }

        if ($v = $this->getVal('instructions', $xmp)) {
            $this->addVal($this->t('Instructions'), $v, $return);
        }

        if (($v = $this->getVal('UserComment', $comp)) || ($v = $this->convertUcs2($this->getVal('Comments', $ifd0)))) {
            $this->addVal($this->t('Comment'), $v, $return);
        }

        if (($d = $this->getVal('DateTimeOriginal', $exif)) || ($v = $this->getVal('dateCreated', $xmp))) {
            if ($d) {
                $d[4] = $d[7] = '-';
                $v = $d;
            }

            $this->addVal($this->t('Date created'), $v, $return);
        }

        if (($w = $this->getVal('ExifImageWidth', $exif)) && ($h = $this->getVal('ExifImageLength', $exif))) {
            if ($ornt = $this->getVal('Orientation', $ifd0)) {
                if ($ornt >= 5) {
                    $tmp = $w;
                    $w = $h;
                    $h = $tmp;
                }
            }

            if (is_array($w)) {
                $w = $w[0];
            }

            if (is_array($h)) {
                $h = $h[0];
            }

        } else {
            $w = $this->getVal('Width', $comp);
            $h = $this->getVal('Height', $comp);
        }

        if ($w && $h) {
            $this->addVal($this->t('Dimensions'), $w . ' x ' . $h, $return);
        }

        if (($v = $this->getVal('Artist', $ifd0)) || ($v = $this->getVal('creator', $xmp))) {
            $this->addVal($this->t('Author'), $v, $return);
        }

        if ($v = $this->getVal('authorsPosition', $xmp)) {
            $this->addVal($this->t('Job title'), $v, $return);
        }

        if ($v = $this->getVal('credit', $xmp)) {
            $this->addVal($this->t('Credits'), $v, $return);
        }

        if ($v = $this->getVal('source', $xmp)) {
            $this->addVal($this->t('Source'), $v, $return);
        }

        if (($v = $this->getVal('Copyright', $ifd0)) || ($v = $this->getVal('rights', $xmp))) {
            $this->addVal($this->t('Copyright'), $v, $return);
        }

        if ($v = $this->getVal('Make', $ifd0)) {
            $this->addVal($this->t('Camera used'), $v, $return);
        }

        if ($v = $this->getVal('Model', $ifd0)) {
            $this->addVal($this->t('Camera used'), $v, $return, null, ' ');
        }

        if ($v = $this->getVal('Software', $ifd0)) {
            $this->addVal($this->t('Software'), $v, $return);
        }

        if ($v = $this->getVal('ExposureTime', $exif)) {
            $this->addVal($this->t('Exposure'), $this->t('%s sec.', array($this->formatRational($v, true))), $return);
        }

        if ($v = $this->getVal('ApertureFNumber', $comp)) {
            $this->addVal($this->t('Exposure'), $v, $return, null, self::EMSP);
        }

        if ($v = $this->getVal('ISOSpeedRatings', $exif)) {
            $this->addVal($this->t('Exposure'), $this->t('ISO-%s', array($v)), $return, null, self::EMSP);
        }

        if ($v = $this->getVal('ExposureBiasValue', $exif)) {
            $e = $this->formatRational($v, false, 1);
            if (substr($e, 0, 1) !== '-') {
                $e = '+' . $e;
            }
            $this->addVal($this->t('Exposure'), $this->t('%s EV', array($e)), $return, null, self::EMSP);
        }

        if ($v = $this->getVal('ExposureProgram', $exif)) {
            $this->addVal($this->t('Exposure program'), $this->formatExposureProgram($v), $return);
        }

        if ($v = $this->getVal('ExposureMode', $exif)) {
            $this->addVal($this->t('Exposure mode'), $this->formatExposureMode($v), $return);
        }

        if ($v = $this->getVal('FocalLength', $exif)) {
            $this->addVal($this->t('Focal length'), $this->t('%g mm', array($this->formatRational($v))), $return);
        }

        if ($v = $this->getVal('FocalLengthIn35mmFilm', $exif)) {
            $this->addVal($this->t('Focal length'), $this->t('(35 mm equivalent: %g mm)', array($v)), $return, null, ' ');
        }

        if ($v = $this->getVal('MaxApertureValue', $exif)) {
            $this->addVal($this->t('Max aperture'), $this->apexToF($this->evalRational($v)), $return);
        }

        if ($v = $this->getVal('MeteringMode', $exif)) {
            $this->addVal($this->t('Metering mode'), $this->formatMeteringMode($v), $return);
        }

        if ($v = $this->getVal('Flash', $exif)) {
            $this->addVal($this->t('Flash mode'), $this->formatFlashMode($v), $return);
        }

        if ($v = $this->getVal('GPSLatitude', $gps)) {
            if (($v = $this->evalGpsCoord($v)) !== null) {
                $ref = $this->getVal('GPSLatitudeRef', $gps);
                $this->addVal($this->t('GPS coordinates'), $this->formatGpsCoord($v, $ref), $return);
                $lat = $this->gpsToDecDegree($v, $ref === 'N');
            }
        }

        if ($v = $this->getVal('GPSLongitude', $gps)) {
            if (($v = $this->evalGpsCoord($v)) !== null) {
                $ref = $this->getVal('GPSLongitudeRef', $gps);
                $this->addVal($this->t('GPS coordinates'), $this->formatGpsCoord($v, $ref), $return, null, self::EMSP);
                $lon = $this->gpsToDecDegree($v, $ref === 'E');
            }
        }

        if ($v = $this->getVal('GPSAltitude', $gps)) {
            if (($v = $this->evalGpsAlt($v)) !== null) {
                $ref = $this->getVal('GPSAltitudeRef', $gps);
                $this->addVal($this->t('GPS altitude'), $this->formatGpsAlt($v, $ref), $return);
            }
        }

        if ($v = $this->getVal('city', $xmp)) {
            $loc['city'] = $v[0];
        }

        if ($v = $this->getVal('state', $xmp)) {
            $loc['state'] = $v[0];
        }

        if ($v = $this->getVal('country', $xmp)) {
            $loc['country'] = $v[0];
        }

        return new Metadata($return, $lat, $lon, $loc);
    }

    protected function getArchiveMetadata($sections) {
        $return = array();

        $comp = $this->getVal('COMPUTED', $sections) ?: array();

        if ($v = $this->getVal('numFiles', $comp)) {
            $this->addVal($this->t('Number of files'), $v, $return);
        }

        if ($v = $this->getVal('comment', $comp)) {
            $this->addVal($this->t('Comment'), $v, $return);
        }

        return new Metadata($return);
    }

    protected function apexToF($val) {
        return 'f/' . sprintf('%01.1f', round(pow(2, $val / 2), 1));
    }

    protected function formatExposureProgram($code) {
        switch ($code) {
            case 0:
                return $this->t('Not defined');
            case 1:
                return $this->t('Manual');
            case 2:
                return $this->t('Normal program');
            case 3:
                return $this->t('Aperture priority');
            case 4:
                return $this->t('Shutter priority');
            case 5:
                return $this->t('Creative program');
            case 6:
                return $this->t('Action program');
            case 7:
                return $this->t('Portrait mode');
            case 8:
                return $this->t('Landscape mode');
            default:
                return null;
        }
    }

    protected function formatExposureMode($mode) {
        switch ($mode) {
            case 0:
                return $this->t('Auto exposure');
            case 1:
                return $this->t('Manual exposure');
            case 2:
                return $this->t('Auto bracket');
            default:
                return null;
        }
    }

    protected function formatMeteringMode($mode) {
        switch ($mode) {
            case 0:
                return $this->t('Unknown');
            case 1:
                return $this->t('Average');
            case 2:
                return $this->t('Center Weighted Average');
            case 3:
                return $this->t('Spot');
            case 4:
                return $this->t('Multi Spot');
            case 5:
                return $this->t('Pattern');
            case 6:
                return $this->t('Partial');
            default:
                return $this->t('Other');
        }
    }

    protected function formatFlashMode($mode) {
        if ($mode & 0x20) {
            return $this->t('No flash function');

        } else {
            $return = $this->t(($mode & 0x01) ? 'Flash' : 'No flash');

            if (($compuls = ($mode & 0x18)) !== 0) {
                $return .= ', ' . $this->t(($compuls === 0x18) ? 'auto' : 'compulsory');
            }

            if ($mode & 0x40) {
                $return .= ', ' . $this->t('red-eye');
            }

            if (($strobe = ($mode & 0x06)) !== 0) {
                $return .= ', ' . $this->t(($strobe === 0x06) ? 'strobe return' : (($strobe === 0x04) ? 'no strobe return' : ''));
            }

            return $return;
        }
    }

    protected function formatFourCc($code) {
        switch ($code) {
            case 'avc1':
                return $this->t('H.264 - MPEG-4 AVC (part 10)');
            default:
                return $code;
        }
    }

    protected function formatRating($rating) {
        $return = '';
        $rating = min($rating, 5);

        $return = str_pad($return, $rating * strlen(self::BLACK_STAR), self::BLACK_STAR);
        $return = str_pad($return, 5 * strlen(self::WHITE_STAR), self::WHITE_STAR);

        return $return;
    }

    protected function evalGpsCoord($coord) {
        $return = null;

        if ((count($coord) === 3) && ($coord[0] !== '0/0')) {
            $return = array();

            foreach ($coord as $c) {
                $return[] = strncmp($c, '0/', 2) ? $this->evalRational($c) : 0;
            }
        }

        return $return;
    }

    protected function formatGpsCoord($coord, $ref) {
        $return = $ref . ' ' . $coord[0] . '°';

        if ($coord[1] || $coord[2]) {
            $return .= ' ' . $coord[1] . '\'';
        }

        if ($coord[2]) {
            $return .= ' ' . round($coord[2], 2) . '"';
        }

        return $return;
    }

    protected function evalGpsAlt($coord) {
        return ($coord !== '0/0') ? $this->evalRational($coord) : null;
    }

    protected function formatGpsAlt($coord, $ref) {
        return (($ref === 1) ? '-' : '') . round($coord, 1) . ' m';
    }

    protected function formatGpsDegree($deg, $posRef, $negRef) {
        $return = ($deg >= 0) ? $posRef : $negRef;
        $deg = abs($deg);

        $v = floor($deg);
        $return .= ' ' . $v . '°';

        $deg = ($deg - $v) * 60;
        $v = floor($deg);
        $return .= ' ' . $v . '\'';

        $deg = ($deg - $v) * 60;
        $v = round($deg, 2);
        $return .= ' ' . $v . '"';

        return $return;
    }

    protected function gpsToDecDegree($coord, $pos) {
        $return = round($coord[0] + ($coord[1] / 60) + ($coord[2] / 3600), 8);

        return $pos? $return : -$return;
    }

    protected function formatSeconds($val) {
        return sprintf("%02d:%02d:%02d", floor($val / 3600), floor(fmod(($val / 60), 60)), round(fmod($val, 60)));
    }

    protected function formatRational($val, $fracIfSmall = false, $precision = 2) {
        if (preg_match('/([\-]?)(\d+)([\/])(\d+)/', $val, $matches) !== false) {
            if ($fracIfSmall && ($matches[2] < $matches[4])) {
                if ($matches[2] !== 1) {
                    $val = $matches[1] . 1 . '/' . round($matches[4] / $matches[2]);
                }

            } else {
                $val = round($this->evalFraction($matches[1], $matches[2], $matches[4]), $precision);
            }
        }

        return $val;
    }

    protected function evalRational($val) {
        if (preg_match('/([\-]?)(\d+)([\/])(\d+)/', $val, $matches) !== false) {
            $val = $this->evalFraction($matches[1], $matches[2], $matches[4]);
        }

        return $val;
    }

    protected function evalFraction($sig, $num, $den) {
        $val = $num / $den;
        if ($sig === '-') {
            $val = -$val;
        }

        return $val;
    }

    protected function formatComments(&$array) {
        foreach ($array as $key => $val) {
            while (substr_compare($val, '&#0;', -4) === 0) {
                $val = substr($val, 0, -4);
                $array[$key] = $val;
            }

            if (!is_numeric($key)) {
                $array[$key] = '('.$key.') '.$val;
            }
        }
    }

    protected function convertUcs2($v) {
        if ($v) {
            $v = rtrim(mb_convert_encoding($v, 'UTF-8', 'UCS-2LE'), "\0");
        }

        return $v;
    }

    protected function getVal($key, &$array, &$array2 = null, &$array3 = null) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (($array2 !== null) && array_key_exists($key, $array2)) {
            return $array2[$key];
        }

        if (($array3 !== null) && array_key_exists($key, $array3)) {
            return $array3[$key];
        }

        return null;
    }

    protected function getValM($key, &$arrays) {
        foreach ($arrays as $array) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return null;
    }

    protected function addVal($key, $val, &$array, $join = null, $sep = null) {
        if (is_array($val)) {
            if (isset($join)) {
                $val = join($join, $val);

            } else if (count($val) <= 1) {
                $val = array_pop($val);
            }
        }

        if (array_key_exists($key, $array)) {
            $prev = $array[$key];

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

        $array[$key] = $val;
    }

    protected function t($text, $parameters = array()) {
        return $this->language->t($text, $parameters);
    }
}
