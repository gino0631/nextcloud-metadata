<?php
namespace OCA\Metadata\Service;

class TiffParser extends FileReader {
    const BYTE = 1;
    const ASCII = 2;
    const SHORT = 3;
    const LONG = 4;
    const RATIONAL = 5;
    const SBYTE = 6;
    const UNDEFINED = 7;
    const SSHORT = 8;
    const SLONG = 9;
    const SRATIONAL = 10;
    const FLOAT = 11;
    const DOUBLE = 12;

    public function parseTiff($hnd, $pos) {
        $data = fread($hnd, 4);

        if (($data === "II\x2A\x00") || ($data === "MM\x00\x2A")) {     // ID
            $intel = ($data[0] === 'I');
            $ifdOffs = self::readInt($hnd, $intel);

            $this->parseTiffIfd($hnd, $pos, $intel, $ifdOffs);
        }
    }

    public function parseTiffIfd($hnd, $pos, $intel, $ifdOffs) {
        while (!feof($hnd) && ($ifdOffs !== 0)) {
            fseek($hnd, $pos + $ifdOffs);               // Go to IFD
            $tagCnt = self::readShort($hnd, $intel);

            for ($i = 0; $i < $tagCnt; $i++) {
                $tagId = self::readShort($hnd, $intel);
                $tagType = self::readShort($hnd, $intel);
                $count = self::readInt($hnd, $intel);
                $offsetOrData = fread($hnd, 4);
                $size = -1;
                switch ($tagType) {
                    case self::BYTE:
                    case self::SBYTE:
                    case self::UNDEFINED:
                        $size = $count;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size);
                        }
                        break;
                    case self::ASCII:
                        $size = $count;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size - 1);
                        }
                        break;
                    case self::SHORT:
                    case self::SSHORT:
                        $size = $count * 2;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size);
                            if ($count === 1) {
                                $offsetOrData = self::unpackShort($intel, $offsetOrData);
                            } else if ($count === 2) {
                                $f = $intel? 'v' : 'n';
                                $d = unpack($f.'a/'.$f.'b', $offsetOrData);
                                $offsetOrData = array($d['a'], $d['b']);
                            }
                        }
                        break;
                    case self::LONG:
                    case self::SLONG:
                        $size = $count * 4;
                        if ($size <= 4) {
                            $offsetOrData = self::unpackInt($intel, $offsetOrData);
                        }
                        break;
                    case self::RATIONAL:
                    case self::SRATIONAL:
                        $size = $count * 8;
                        break;
                }
                if ($size > 4) {
                    $offsetOrData = self::unpackInt($intel, $offsetOrData);
                }

                if ($size > 0) {
                    $curr = ftell($hnd);

                    $result = $this->processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData);
                    if ($result) {
                        return $result;
                    }

                    fseek($hnd, $curr);
                }
            }

            $ifdOffs = self::readInt($hnd, $intel);
            if (($ifdOffs !== 0) && ($ifdOffs < ftell($hnd))) {         // Never go back
                $ifdOffs = 0;
            }
        }

        return null;
    }

    protected function processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData) {
        return null;
    }
}
