<?php
namespace OCA\Metadata\Service;


class PngMetadata {

    private $textChunks = array();

    public static function fromFile($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $obj = new PngMetadata();
                $obj->readPng($hnd);

                return $obj;
            } finally {
                fclose($hnd);
            }
        }

        return null;
    }

    public function getTextChunks() {
        return $this->textChunks;
    }

    protected function readPng($hnd) {
        if (fread($hnd, 8) === "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a") {
            while ($chunkHeader = fread($hnd, 8)) {
                $pos = ftell($hnd);
                $chunk = unpack('Nsize/a4type', $chunkHeader);
                switch ($chunk['type']) {
                    case "tEXt":
                        $data = fread($hnd, $chunk['size']);
                        $value =  explode("\x00", trim($data), 2);
                        $this->textChunks[] = [
                            "keyword" => $value[0],
                            "text" => $value[1],
                        ];
                        break;
                    case "zTXt":
                        $data = fread($hnd, $chunk['size']);
                        $value =  explode("\x00", trim($data), 2);
                        $contents =  substr($value[1], 1);
                        $this->textChunks[] = [
                            "keyword" => $value[0],
                            "text" => $this->uncompress($value[1][0], $contents),
                        ];
                        break;
                    case "iTXt":
                        $data = fread($hnd, $chunk['size']);
                        $value =  explode("\x00", trim($data), 2);
                        $contents =  explode("\x00", substr($value[1], 2), 3);
                        $this->textChunks[] = [
                            "keyword" => $value[0],
                            "text" => $this->uncompress($value[1][1], $contents[2], $value[1][0]),
                            "language" => $contents[0],
                            "translated" => $contents[1],
                        ];
                        break;
                }
                fseek($hnd, $pos + $chunk['size'] + 4);
            }
        }
    }

    protected function uncompress($method, $contents, $flag = "\x01") {
        if ($flag === "\x01") {
            switch ($method) {
                case "\x00":
                    return gzuncompress($contents);
                default:
                    return false;
            }
        } else {
            return $contents;
        }
    }
}