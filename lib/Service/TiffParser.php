<?php
namespace OCA\Metadata\Service;

class TiffParser {
    const BYTE = 1;
    const ASCII = 2;
    const SHORT = 3;
    const LONG = 4;
    const RATIONAL = 5;

    public function parseTiff($hnd, $pos) {
        $data = fread($hnd, 4);

        if (($data === "II\x2A\x00") || ($data === "MM\x00\x2A")) {     // ID
            $intel = ($data[0] === 'I');
            $ifdOffs = $this->readInt($hnd, $intel);

            $this->parseTiffIfd($hnd, $pos, $intel, $ifdOffs);
        }
    }

    public function parseTiffIfd($hnd, $pos, $intel, $ifdOffs) {
        while (!feof($hnd) && ($ifdOffs !== 0)) {
            fseek($hnd, $pos + $ifdOffs);               // Go to IFD
            $tagCnt = $this->readShort($hnd, $intel);

            for ($i = 0; $i < $tagCnt; $i++) {
                $tagId = $this->readShort($hnd, $intel);
                $tagType = $this->readShort($hnd, $intel);
                $count = $this->readInt($hnd, $intel);
                $offsetOrData = fread($hnd, 4);
                $size = -1;
                switch ($tagType) {
                    case self::BYTE:
                        $size = $count;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size);
                        }
                        break;
                    case self::ASCII:
                        $size = $count;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size- 1);
                        }
                        break;
                    case self::SHORT:
                        $size = $count * 2;
                        if ($size <= 4) {
                            $offsetOrData = substr($offsetOrData, 0, $size);
                            if ($count === 1) {
                                $offsetOrData = $this->unpackShort($intel, $offsetOrData);
                            } else {
                                $f = $intel? 'v' : 'n';
                                $d = unpack($f.'a/'.$f.'b', $offsetOrData);
                                $offsetOrData = array($d['a'], $d['b']);
                            }
                        }
                        break;
                    case self::LONG:
                        $size = $count * 4;
                        if ($size <= 4) {
                            $offsetOrData = $this->unpackInt($intel, $offsetOrData);
                        }
                        break;
                    case self::RATIONAL:
                        $size = $count * 8;
                        break;
                }
                if ($size > 4) {
                    $offsetOrData = $this->unpackInt($intel, $offsetOrData);
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

            $ifdOffs = $this->readInt($hnd, $intel);
            if (($ifdOffs !== 0) && ($ifdOffs < ftell($hnd))) {         // Never go back
                $ifdOffs = 0;
            }
        }

        return null;
    }

    protected function processTag($hnd, $pos, $intel, $tagId, $tagType, $count, $size, $offsetOrData) {
        return null;
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
}
