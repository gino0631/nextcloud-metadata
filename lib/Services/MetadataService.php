<?php
namespace OCA\Metadata\Services;

use OCP\IConfig;
use OCP\IL10N;
use OC\Files\Filesystem;
use OCA\Metadata\GetID3\getID3;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MetadataService {

    const EXPOSURE_PROGRAMS = array(
        0 => 'Not defined',
        1 => 'Manual',
        2 => 'Normal program',
        3 => 'Aperture priority',
        4 => 'Shutter priority',
        5 => 'Creative program',
        6 => 'Action program',
        7 => 'Portrait mode',
        8 => 'Landscape mode'
    );

    const EXPOSURE_MODES = array(
        0 => 'Auto exposure',
        1 => 'Manual exposure',
        2 => 'Auto bracket'
    );

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

    const FOUR_CC = array(
        'avc1' => 'H.264 - MPEG-4 AVC (part 10)'
    );

    public function getMetadata($source, $language) {
        $file = Filesystem::getLocalFile($source);
        if (!$file) {
            throw new \Exception($language->t('File not found.'));
        }

        $metadata = null;
        $lat = null;
        $lon = null;

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
            case 'video/x-flv':
            case 'video/x-matroska':
            case 'video/x-msvideo':
                if ($sections = $this->readId3($file)) {
                    $metadata = $this->getAvMetadata($sections, $language, $lat, $lon);
//                    $this->dump($sections, $metadata);
                }
                break;

            case 'image/jpeg':
                if ($sections = $this->readExif($file)) {
                    $sections['XMP'] = $this->readJpegXmp($file);
                    $metadata = $this->getImageMetadata($sections, $language, $lat, $lon);
//                    $this->dump($sections, $metadata);
                }
                break;

            case 'image/tiff':
                if ($sections = $this->readExif($file)) {
                    $sections['XMP'] = $this->readTiffXmp($file);
                    $metadata = $this->getImageMetadata($sections, $language, $lat, $lon);
//                    $this->dump($sections, $metadata);
                }
                break;

            default:
                throw new \Exception($language->t('Unsupported MIME type "%s".', array($mimetype)));
        }
        return new Metadata($metadata, $lat, $lon);
    }

    protected function readId3($file) {
        $getId3 = new getID3();
        $getId3->option_save_attachments = getID3::ATTACHMENTS_NONE;

        return $getId3->analyze($file);
    }

    protected function readExif($file) {
        if (!function_exists('exif_read_data')) {
            throw new Exception($this->language->t('EXIF support is missing; you might need to install an appropriate package for your system.'));
        }

        return exif_read_data($file, 0, true);
    }

    protected function readJpegXmp($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $data = fread($hnd, 2);

                if ($data === "\xFF\xD8") {     // SOI (Start Of Image)
                    $data = fread($hnd, 2);

                    // While not EOF, tag is valid, and not SOS (Start Of Scan) or EOI (End Of Image)
                    while (!feof($hnd) && ($data[0] === "\xFF") && ($data[1] !== "\xDA") && ($data[1] !== "\xD9")) {
                        $size = 0;
                        if ((ord($data[1]) < 0xD0) || (ord($data[1]) > 0xD7)) {     // All segments but RSTn have size bytes
                            $size = $this->unpackShort(false, fread($hnd, 2)) - 2;
                        }

                        if (($data[1] === "\xE1") && ($size > 29)) {                // APP1 with enough data
                            $data = fread($hnd, 29);
                            $size -= 29;

                            if ($data === 'http://ns.adobe.com/xap/1.0/'."\x00") {
                                $xmpMetadata = new XmpMetadata(fread($hnd, $size));
                                return $xmpMetadata->getArray();
                            }
                        }

                        if ($size > 0) {
                            fseek($hnd, $size, SEEK_CUR);
                        }

                        $data = fread($hnd, 2);
                    }
                }

            } finally {
                fclose($hnd);
            }
        }

        return null;
    }

    protected function readTiffXmp($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $data = fread($hnd, 4);

                if (($data === "II\x2A\x00") || ($data === "MM\x00\x2A")) {     // ID
                    $intel = ($data[0] === 'I');
                    $ifdOffs = $this->unpackInt($intel, fread($hnd, 4));

                    while (!feof($hnd) && ($ifdOffs !== 0)) {
                        fseek($hnd, $ifdOffs, SEEK_SET);                // Go to IFD
                        $tagCnt = $this->unpackShort($intel, fread($hnd, 2));

                        for ($i = 0; $i < $tagCnt; $i++) {
                            $tagId = $this->unpackShort($intel, fread($hnd, 2));
                            fread($hnd, 2);     // TagType

                            if ($tagId === 0x02BC) {
                                $count = $this->unpackInt($intel, fread($hnd, 4));
                                $offset = $this->unpackInt($intel, fread($hnd, 4));
                                fseek($hnd, $offset, SEEK_SET);         // Go to XMP

                                $xmpMetadata = new XmpMetadata(fread($hnd, $count));
                                return $xmpMetadata->getArray();

                            } else {
                                fread($hnd, 8);
                            }
                        }

                        $ifdOffs = $this->unpackInt($intel, fread($hnd, 4));
                        if (($ifdOffs !== 0) && ($ifdOffs < ftell($hnd))) {     // Never go back
                            $ifdOffs = 0;
                        }
                    }
                }

            } finally {
                fclose($hnd);
            }
        }

        return null;
    }

    protected function unpackShort($intel, $data) {
        return unpack(($intel? 'v' : 'n').'d', $data)['d'];
    }

    protected function unpackInt($intel, $data) {
        return unpack(($intel? 'V' : 'N').'d', $data)['d'];
    }

    protected function getAvMetadata($sections, $language, &$lat, &$lon) {
        $return = array();

        $audio = $this->getVal('audio', $sections) ?: array();
        $video = $this->getVal('video', $sections) ?: array();
        $tags = $this->getVal('tags_html', $sections) ?: array();
        $vorbis = $this->getVal('vorbiscomment', $tags) ?: array();
        $id3v2 = $this->getVal('id3v2', $tags) ?: array();
        $id3v1 = $this->getVal('id3v1', $tags) ?: array();
        $riff = $this->getVal('riff', $tags) ?: array();
        $quicktime = $this->getVal('quicktime', $tags) ?: array();
        $matroska = $this->getVal('matroska', $tags) ?: array();

        krsort($tags);  // make a predictable order with 'id3v2' before 'id3v1'

        if ($v = $this->getValM('title', $tags)) {
            $this->addValT($language, 'Title', $v, $return);
        }

        if ($v = $this->getValM('artist', $tags)) {
            $this->addValT($language, 'Artist', $v, $return);
        }

        if ($v = $this->getVal('playtime_seconds', $sections)) {
            $this->addValT($language, 'Length', $this->formatSeconds($v), $return);
        }

        if (($x = $this->getVal('resolution_x', $video)) && ($y = $this->getVal('resolution_y', $video))) {
            $this->addValT($language, 'Dimensions', $x . ' x ' . $y, $return);
        }

        if ($v = $this->getVal('frame_rate', $video)) {
            $this->addValT($language, 'Frame rate', $language->t('%g fps', array($v)), $return);
        }

        if ($v = $this->getVal('bitrate', $sections)) {
            $this->addValT($language, 'Bit rate', $language->t('%s kbps', array(floor($v/1000))), $return);
        }

        if ($v = $this->getVal('author', $quicktime)) {
            $this->addValT('Author', $v, $return);
        }

        if ($v = $this->getVal('copyright', $quicktime)) {
            $this->addValT('Copyright', $v, $return);
        }

        if ($v = $this->getVal('make', $quicktime)) {
            $this->addValT('Camera used', $v, $return);
        }

        if ($v = $this->getVal('model', $quicktime)) {
            $this->addValT('Camera used', $v, $return);
        }

        if ($v = $this->getVal('com.android.version', $quicktime)) {
            $this->addValT('Android version', $v, $return);
        }

        if ($v = $this->getVal('codec', $video)) {
            $this->addValT($language, 'Video codec', $v, $return);
        } else if ($v = $this->getVal('fourcc', $video)) {
            $this->addValT('Video codec', $this->formatFourCc($v), $return);
        }

        if ($v = $this->getVal('bits_per_sample', $video)) {
            $this->addValT($language, 'Video sample size', $language->t('%s bit', array($v)), $return);
        }

        if ($v = $this->getVal('codec', $audio)) {
            $this->addValT($language, 'Audio codec', $v, $return);
        }

        if ($v = $this->getVal('channels', $audio)) {
            $this->addValT($language, 'Audio channels', $v, $return);
        }

        if ($v = $this->getVal('sample_rate', $audio)) {
            $this->addValT($language, 'Audio sample rate', $language->t('%s kHz', array($v/1000)), $return);
        }

        if ($v = $this->getVal('bits_per_sample', $audio)) {
            $this->addValT($language, 'Audio sample size', $language->t('%s bit', array($v)), $return);
        }

        if ($v = $this->getValM('album', $tags) ?: $this->getVal('product', $riff)) {
            $this->addValT($language, 'Album', $v, $return);
        }

        if ($v = $this->getVal('tracknumber', $vorbis) ?: $this->getVal('part', $riff) ?: $this->getVal('track_number', $id3v2) ?: $this->getVal('track', $id3v1)) {
            $this->addValT($language, 'Track #', $v, $return);
        }

        if ($v = $this->getVal('date', $vorbis) ?: $this->getVal('creationdate', $riff) ?: $this->getVal('creation_date', $quicktime) ?: $this->getVal('year', $vorbis, $id3v2, $id3v1)) {
            $isYear = is_array($v) && (count($v) == 1) && (strlen($v[0]) == 4);
            $this->addValT($isYear ? 'Year' : 'Date', $v, $return);
        }

        if ($v = $this->getValM('genre', $tags)) {
            $this->addValT($language, 'Genre', $v, $return);
        }

        if ($v = $this->getVal('description', $vorbis) ?: $this->getValM('comment', $tags)) {
            if (is_array($v)) {
                $this->formatComments($v);
            }

            $this->addValT($language, 'Comment', $v, $return);
        }

        if ($v = $this->getValM('encoded_by', $tags)) {
            $this->addValT($language, 'Encoded by', $v, $return);
        }

        if ($v = $this->getVal('software', $riff) ?: $this->getVal('encoding_tool', $quicktime) ?: $this->getVal('encoder', $matroska, $audio)) {
            $this->addValT($language, 'Encoding tool', $v, $return);
        }

        if ($v = $this->getVal('gps_latitude', $quicktime)) {
            $lat = $v[0];
            $this->addValT('GPS coordinates', $this->formatGpsDegree($lat, 'N', 'S'), $return);
        }

        if ($v = $this->getVal('gps_longitude', $quicktime)) {
            $lon = $v[0];
            $this->addValT('GPS coordinates', $this->formatGpsDegree($lon, 'E', 'W'), $return, null, '&emsp;');
        }

        return $return;
    }

    protected function getImageMetadata($sections, $language, &$lat, &$lon) {
        $return = array();

        $comp = $this->getVal('COMPUTED', $sections) ?: array();
        $ifd0 = $this->getVal('IFD0', $sections) ?: array();
        $exif = $this->getVal('EXIF', $sections) ?: array();
        $gps = $this->getVal('GPS', $sections) ?: array();
        $xmp = $this->getVal('XMP', $sections) ?: array();

        if ($v = $this->getVal('title', $xmp)) {
            $this->addValT($language, 'Title', $v, $return);
        }

        if ($v = $this->getVal('description', $xmp)) {
            $this->addValT($language, 'Description', $v, $return);
        }

        if ($v = $this->getVal('people', $xmp)) {
            $this->addValT($language, 'People', $v, $return);
        }

        if ($v = $this->getVal('Subject', $ifd0)) {
            $this->addValT($language, 'Subject', $v, $return);
        }

        if ($v = $this->getVal('tags', $xmp)) {
            $this->addValT($language, 'Tags', $v, $return);
        }

        if ($v = $this->getVal('subject', $xmp)) {
            $this->addValT($language, 'Tags', $v, $return);
        }

        if ($v = $this->getVal('Rating', $xmp)) {
            $this->addValT($language, 'Rating', $v, $return);
        }

        if (($v = $this->getVal('Comments', $ifd0)) || ($v = $this->getVal('UserComment', $comp))) {
            $this->addValT($language, 'Comment', $v, $return);
        }

        if ($v = $this->getVal('DateTimeOriginal', $exif)) {
            $v[4] = $v[7] = '-';
            $this->addValT($language, 'Date taken', $v, $return);
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
            $this->addValT($language, 'Dimensions', $w . ' x ' . $h, $return);
        }

        if ($v = $this->getVal('Artist', $ifd0)) {
            $this->addValT($language, 'Artist', $v, $return);
        }

        if ($v = $this->getVal('Make', $ifd0)) {
            $this->addValT($language, 'Camera used', $v, $return);
        }

        if ($v = $this->getVal('Model', $ifd0)) {
            $this->addValT($language, 'Camera used', $v, $return);
        }

        if ($v = $this->getVal('Software', $ifd0)) {
            $this->addValT($language, 'Software', $v, $return);
        }

        if ($v = $this->getVal('ExposureTime', $exif)) {
            $this->addValT($language, 'Exposure', $language->t('%s sec.', array($this->formatRational($v, true))), $return);
        }

        if ($v = $this->getVal('ApertureFNumber', $comp)) {
            $this->addValT($language, 'Exposure', $v, $return, null, '&emsp;');
        }

        if ($v = $this->getVal('ISOSpeedRatings', $exif)) {
            $this->addValT($language, 'Exposure', $language->t('ISO-%s', array($v)), $return, null, '&emsp;');
        }

        if ($v = $this->getVal('ExposureProgram', $exif)) {
            $this->addValT($language, 'Exposure program', $this->formatExposureProgram($v, $language), $return);
        }

        if ($v = $this->getVal('ExposureMode', $exif)) {
            $this->addValT($language, 'Exposure mode', $this->formatExposureMode(v, $language), $return);
        }

        if ($v = $this->getVal('ExposureBiasValue', $exif)) {
            $this->addValT($language, 'Exposure bias', $language->t('%s step', array($this->formatRational($v))), $return);
        }

        if ($v = $this->getVal('FocalLength', $exif)) {
            $this->addValT($language, 'Focal length', $language->t('%g mm', array($this->formatRational($v))), $return);
        }

        if ($v = $this->getVal('FocalLengthIn35mmFilm', $exif)) {
            $this->addValT($language, 'Focal length', $language->t('(35 mm equivalent: %g mm)', array($v)), $return);
        }

        if ($v = $this->getVal('MaxApertureValue', $exif)) {
            $this->addValT($language, 'Max aperture', $this->apexToF($this->evalRational($v)), $return);
        }

        if ($v = $this->getVal('MeteringMode', $exif)) {
            $this->addValT($language, 'Metering mode', $this->formatMeteringMode($v, $language), $return);
        }

        if ($v = $this->getVal('Flash', $exif)) {
            $this->addValT($language, 'Flash mode', $this->formatFlashMode($v, $language), $return);
        }

        if ($v = $this->getVal('GPSLatitude', $gps)) {
            $ref = $this->getVal('GPSLatitudeRef', $gps);
            $this->addValT($language, 'GPS coordinates', $this->formatGpsCoord($v, $ref), $return);
            $lat = $this->gpsToDecDegree($v, $ref == 'N');
        }

        if ($v = $this->getVal('GPSLongitude', $gps)) {
            $ref = $this->getVal('GPSLongitudeRef', $gps);
            $this->addValT($language, 'GPS coordinates', $this->formatGpsCoord($v, $ref), $return, null, '&emsp;');
            $lon = $this->gpsToDecDegree($v, $ref == 'E');
        }

        return $return;
    }

    protected function apexToF($val) {
        return 'f/' . sprintf('%01.1f', round(pow(2, $val / 2), 1));
    }

    protected function formatExposureProgram($code, $language) {
        return $language->t(MetadataService::EXPOSURE_PROGRAMS[$code]);
    }

    protected function formatExposureMode($mode, $language) {
        return $language->t(MetadataService::EXPOSURE_MODES[$mode]);
    }

    protected function formatMeteringMode($mode, $language) {
        if (!array_key_exists($mode, MetadataService::METERING_MODES)) {
            $mode = 255;
        }

        return $language->t(MetadataService::METERING_MODES[$mode]);
    }

    protected function formatFlashMode($mode, $language) {
        if ($mode & 0x20) {
            return $language->t('No flash function');

        } else {
            $return = $language->t(($mode & 0x01) ? 'Flash' : 'No flash');

            if (($compuls = ($mode & 0x18)) != 0) {
                $return .= ', ' . $language->t(($compuls == 0x18) ? 'auto' : 'compulsory');
            }

            if ($mode & 0x40) {
                $return .= ', ' . $language->t('red-eye');
            }

            if (($strobe = ($mode & 0x06)) != 0) {
                $return .= ', ' . $language->t(($strobe == 0x06) ? 'strobe return' : (($strobe == 0x04) ? 'no strobe return' : ''));
            }

            return $return;
        }
    }

    protected function formatFourCc($code) {
        return array_key_exists($code, MetadataController::FOUR_CC) ? MetadataController::FOUR_CC[$code] . ' (' . $code .')' : $code;
    }

    protected function formatGpsCoord($coord, $ref) {
        $return = $ref . ' ' . $this->evalRational($coord[0]) . '°';

        if (($coord[1] != '0/1') || ($coord[2] != '0/1')) {
            $return .= ' ' . $this->evalRational($coord[1]) . '\'';
        }

        if ($coord[2] != '0/1') {
            $return .= ' ' . round($this->evalRational($coord[2]), 2) . '"';
        }

        return $return;
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
        $return = round($this->evalRational($coord[0]) + ($this->evalRational($coord[1]) / 60) + ($this->evalRational($coord[2]) / 3600), 8);

        return $pos? $return : -$return;
    }

    protected function formatSeconds($val) {
        return sprintf("%02d:%02d:%02d", floor($val / 3600), floor(fmod(($val / 60), 60)), round(fmod($val, 60)));
    }

    protected function formatRational($val, $fracIfSmall = false) {
        if (preg_match('/([\-]?)(\d+)([\/])(\d+)/', $val, $matches) !== false) {
            if ($fracIfSmall && ($matches[2] < $matches[4])) {
                if ($matches[2] != 1) {
                    $val = $matches[1] . 1 . '/' . round($matches[4] / $matches[2]);
                }

            } else {
                $val = round($this->evalFraction($matches[1], $matches[2], $matches[4]), 2);
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
        if ($sig == '-') {
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

    protected function getVal($key, &$array, &$array2 = null, &$array3 = null) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (($array2 != null) && array_key_exists($key, $array2)) {
            return $array2[$key];
        }

        if (($array3 != null) && array_key_exists($key, $array3)) {
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
        if (is_null($join)) {
            $join = '<br>';
        }

        if (is_null($sep)) {
            $sep = ' ';
        }

        if (is_array($val)) {
            $val = join($join, $val);
        }

        if (array_key_exists($key, $array)) {
            $prev = $array[$key];
            if (substr($val, 0, strlen($prev)) != $prev) {
                $val = $prev . $sep . $val;
            }
        }

        $array[$key] = $val;
    }

    protected function addValT($language, $key, $val, &$array, $join = null, $sep = null) {
        $this->addVal($language->t($key), $val, $array, $join, $sep);
    }

    protected function dump(&$data, &$array, $prefix = '') {
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $this->dump($val, $array, $prefix . $key . '.');

            } else {
                $this->addVal($prefix . utf8_encode($key), utf8_encode($val), $array);
            }
        }
    }
}
