<?php
namespace OCA\Metadata\Service;

use OC\Files\Filesystem;
use OCA\Metadata\AppInfo\Application;
use OCA\Metadata\GetID3\getID3;

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

    protected $language;

    public function __construct() {
        $this->language = \OC::$server->getL10N(Application::APP_NAME);
    }

    /**
     * @NoAdminRequired
     */
    public function getMetadata($source) {
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
//                        $this->dump($sections, $metadata);
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

            case 'image/jpeg':
                if ($sections = $this->readExif($file)) {
                    $sections['XMP'] = $this->readJpegXmpIptc($file);
                    if (!array_key_exists('GPS', $sections)) {
                        $sections['GPS'] = $this->readJpegGps($file);
                    }
                    $metadata = $this->getImageMetadata($sections);
//                        $this->dump($sections, $metadata);
                }
                break;

            case 'image/tiff':
                if ($sections = $this->readExif($file)) {
                    $sections['XMP'] = $this->readTiffXmpIptc($file);
                    $metadata = $this->getImageMetadata($sections);
//                        $this->dump($sections, $metadata);
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

            default:
                throw new \Exception($this->t('Unsupported MIME type "%s".', array($mimetype)));
        }

        return $metadata;
    }

    protected function readId3($file) {
        $getId3 = new getID3();
        $getId3->option_save_attachments = getID3::ATTACHMENTS_NONE;

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

        return exif_read_data($file, 0, true);
    }

    protected function readJpegGps($file) {
        return $this->readJpegFile($file, function($hnd, $marker, $size) {
            if (($marker === "\xE1") && ($size > 14)) {                // APP1 with enough data
                $data = fread($hnd, 6);

                if ($data === 'Exif'."\x00\x00") {
                    $pos = ftell($hnd);

                    return $this->readTiff($hnd, $pos, function($hnd, $intel, $tagId, $tagType, $count, $offset) use($pos) {
                        if ($tagId === 0x8825) {
                            $gps = array();
                            $this->readTiffIfd($hnd, $pos, $intel, $offset, function($hnd, $intel, $tagId, $tagType, $count, $offsetOrData) use($pos, &$gps) {
                                switch($tagId) {
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
                            });

                            return $gps;
                        }
                    });
                }
            }
        });
    }

    protected function readJpegXmpIptc($file) {
        $xmp = array();
        $iptc = array();

        $this->readJpegFile($file, function($hnd, $marker, $size) use (&$xmp, &$iptc) {
            if (($marker === "\xE1") && ($size > 29)) {                // APP1 with enough data
                $data = fread($hnd, 29);
                $size -= 29;

                if ($data === 'http://ns.adobe.com/xap/1.0/'."\x00") {
                    $xmpMetadata = XmpMetadata::fromData(fread($hnd, $size));
                    $xmp = $xmpMetadata->getArray();
                }

            } else if (($marker === "\xED") && ($size > 0)) {          // APP13
                $iptcMetadata = IptcMetadata::fromData(fread($hnd, $size));
                $iptc = $iptcMetadata->getArray();
            }
        });

        return array_merge($iptc, $xmp);
    }

    protected function readJpegFile($file, $callback) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $data = fread($hnd, 2);

                if ($data === "\xFF\xD8") {     // SOI (Start Of Image)
                    $data = fread($hnd, 2);

                    // While not EOF, tag is valid, and not SOS (Start Of Scan) or EOI (End Of Image)
                    while (!feof($hnd) && ($data[0] === "\xFF") && ($data[1] !== "\xDA") && ($data[1] !== "\xD9")) {
                        if ((ord($data[1]) < 0xD0) || (ord($data[1]) > 0xD7)) {     // All segments but RSTn have size bytes
                            $size = $this->readShort($hnd, false) - 2;

                            if ($size > 0) {
                                $pos = ftell($hnd);
                                $result = call_user_func($callback, $hnd, $data[1], $size);
                                if ($result) {
                                    return $result;
                                }

                                fseek($hnd, $pos + $size);
                            }
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

    protected function readTiffXmpIptc($file) {
        $xmp = array();
        $iptc = array();

        $this->readTiffFile($file, function($hnd, $intel, $tagId, $tagType, $count, $offset) use (&$xmp, &$iptc) {
            if ($tagId === 0x02BC) {
                fseek($hnd, $offset);           // Go to XMP

                $xmpMetadata = XmpMetadata::fromData(fread($hnd, $count));
                $xmp = $xmpMetadata->getArray();

            } else if ($tagId === 0x83BB) {
                fseek($hnd, $offset);           // Go to IPTC
                $iptcMetadata = IptcMetadata::fromData(fread($hnd, $count));
                $iptc = $iptcMetadata->getArray();
            }
        });

        return array_merge($iptc, $xmp);
    }

    protected function readTiffFile($file, $callback) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                return $this->readTiff($hnd, 0, $callback);

            } finally {
                fclose($hnd);
            }
        }

        return null;
    }

    protected function readTiff($hnd, $pos, $callback) {
        $data = fread($hnd, 4);

        if (($data === "II\x2A\x00") || ($data === "MM\x00\x2A")) {     // ID
            $intel = ($data[0] === 'I');
            $ifdOffs = $this->readInt($hnd, $intel);

            return $this->readTiffIfd($hnd, $pos, $intel, $ifdOffs, $callback);
        }
    }

    protected function readTiffIfd($hnd, $pos, $intel, $ifdOffs, $callback) {
        while (!feof($hnd) && ($ifdOffs !== 0)) {
            fseek($hnd, $pos + $ifdOffs);               // Go to IFD
            $tagCnt = $this->readShort($hnd, $intel);

            for ($i = 0; $i < $tagCnt; $i++) {
                $tagId = $this->readShort($hnd, $intel);
                $tagType = $this->readShort($hnd, $intel);
                $count = $this->readInt($hnd, $intel);
                $count = $count * (($tagType === 3) ? 2 : ($tagType === 4) ? 4 : ($tagType === 5) ? 8 : 1);
                $offsetOrData = ($count <= 4) ? substr(fread($hnd, 4), 0, $count - 1) : $this->readInt($hnd, $intel);
                $curr = ftell($hnd);

                $result = call_user_func($callback, $hnd, $intel, $tagId, $tagType, $count, $offsetOrData);
                if ($result) {
                    return $result;
                }

                fseek($hnd, $curr);
            }

            $ifdOffs = $this->readInt($hnd, $intel);
            if (($ifdOffs !== 0) && ($ifdOffs < ftell($hnd))) {         // Never go back
                $ifdOffs = 0;
            }
        }
    }

    protected function readShort($hnd, $intel) {
        return $this->unpackShort($intel, fread($hnd, 2));
    }

    protected function readInt($hnd, $intel) {
        return $this->unpackInt($intel, fread($hnd, 4));
    }

    protected function readRat($hnd, $intel) {
        return $this->readInt($hnd, $intel) . '/' . $this->readInt($hnd, $intel);
    }

    protected function unpackShort($intel, $data) {
        return unpack(($intel? 'v' : 'n').'d', $data)['d'];
    }

    protected function unpackInt($intel, $data) {
        return unpack(($intel? 'V' : 'N').'d', $data)['d'];
    }

    protected function getAvMetadata($sections) {
        $return = array();
        $lat = null;
        $lon = null;

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
            $this->addVal($this->t('GPS coordinates'), $this->formatGpsDegree($lon, 'E', 'W'), $return, null, '&emsp;');
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

        if ($v = $this->getVal('description', $xmp)) {
            $this->addVal($this->t('Description'), $v, $return);
        }

        if ($v = $this->getVal('captionWriter', $xmp)) {
            $this->addVal($this->t('Description writer'), $v, $return);
        }

        if ($v = $this->getVal('people', $xmp)) {
            $this->addVal($this->t('People'), $v, $return);
        }

        if ($v = $this->getVal('tags', $xmp)) {
            $this->addVal($this->t('Tags'), $v, $return);
        }

        if ($v = $this->getVal('subject', $xmp)) {
            $this->addVal($this->t('Keywords'), $v, $return);
        }

        if ($v = $this->getVal('instructions', $xmp)) {
            $this->addVal($this->t('Instructions'), $v, $return);
        }

        if ($v = $this->getVal('UserComment', $comp)) {
            $this->addVal($this->t('Comment'), $v, $return);

        } else if ($v = $this->getVal('Comments', $ifd0)) {
            $this->addVal($this->t('Comment'), rtrim(mb_convert_encoding($v, 'UTF-8', 'UTF-16LE'), "\0"), $return);
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
            $this->addVal($this->t('Exposure'), $v, $return, null, '&emsp;');
        }

        if ($v = $this->getVal('ISOSpeedRatings', $exif)) {
            $this->addVal($this->t('Exposure'), $this->t('ISO-%s', array($v)), $return, null, '&emsp;');
        }

        if ($v = $this->getVal('ExposureProgram', $exif)) {
            $this->addVal($this->t('Exposure program'), $this->formatExposureProgram($v), $return);
        }

        if ($v = $this->getVal('ExposureMode', $exif)) {
            $this->addVal($this->t('Exposure mode'), $this->formatExposureMode($v), $return);
        }

        if ($v = $this->getVal('ExposureBiasValue', $exif)) {
            $this->addVal($this->t('Exposure bias'), $this->t('%s step', array($this->formatRational($v))), $return);
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
            $ref = $this->getVal('GPSLatitudeRef', $gps);
            $this->addVal($this->t('GPS coordinates'), $this->formatGpsCoord($v, $ref), $return);
            $lat = $this->gpsToDecDegree($v, $ref === 'N');
        }

        if ($v = $this->getVal('GPSLongitude', $gps)) {
            $ref = $this->getVal('GPSLongitudeRef', $gps);
            $this->addVal($this->t('GPS coordinates'), $this->formatGpsCoord($v, $ref), $return, null, '&emsp;');
            $lon = $this->gpsToDecDegree($v, $ref === 'E');
        }

        if ($v = $this->getVal('GPSAltitude', $gps)) {
            $ref = $this->getVal('GPSAltitudeRef', $gps);
            $this->addVal($this->t('GPS altitude'), $this->formatGpsAlt($v, $ref), $return);
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

    protected function apexToF($val) {
        return 'f/' . sprintf('%01.1f', round(pow(2, $val / 2), 1));
    }

    protected function formatExposureProgram($code) {
        return $this->t(MetadataService::EXPOSURE_PROGRAMS[$code]);
    }

    protected function formatExposureMode($mode) {
        return $this->t(MetadataService::EXPOSURE_MODES[$mode]);
    }

    protected function formatMeteringMode($mode) {
        if (!array_key_exists($mode, MetadataService::METERING_MODES)) {
            $mode = 255;
        }

        return $this->t(MetadataService::METERING_MODES[$mode]);
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
        return array_key_exists($code, MetadataService::FOUR_CC) ? MetadataService::FOUR_CC[$code] . ' (' . $code .')' : $code;
    }

    protected function formatGpsCoord($coord, $ref) {
        $return = $ref . ' ' . $this->evalRational($coord[0]) . '°';

        if (($coord[1] !== '0/1') || ($coord[2] !== '0/1')) {
            $return .= ' ' . $this->evalRational($coord[1]) . '\'';
        }

        if ($coord[2] !== '0/1') {
            $return .= ' ' . round($this->evalRational($coord[2]), 2) . '"';
        }

        return $return;
    }

    protected function formatGpsAlt($coord, $ref) {
        return (($ref === 1) ? '-' : '') . round($this->evalRational($coord), 1) . ' m';
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
                if ($matches[2] !== 1) {
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
