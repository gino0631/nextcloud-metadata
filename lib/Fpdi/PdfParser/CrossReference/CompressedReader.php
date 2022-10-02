<?php

/**
 * @package   OCA\Metadata\Fpdi
 * @license   https://opensource.org/licenses/AGPL-3.0 GNU Affero General Public License version 3
 */

namespace OCA\Metadata\Fpdi\PdfParser\CrossReference;

use OCA\Metadata\Fpdi\PdfParser\PdfParser;
use OCA\Metadata\Fpdi\PdfParser\StreamReader;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfArray;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfDictionary;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfNumeric;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfStream;

/**
 * Class CompressedReader
 *
 * This reader allows parsing of compressed cross-references streams.
 */
class CompressedReader implements ReaderInterface
{
    /**
     * @var PdfStream
     */
    protected $stream;

    /**
     * @var StreamReader
     */
    protected $streamReader;

    /**
     * @var PdfDictionary
     */
    protected $dict;

    /**
     * @var array
     */
    protected $subsections = [];

    /**
     * @var array
     */
    protected $fieldSizes = [];

    /**
     * @var int
     */
    protected $fieldsSize;

    /**
     * CompressedReader constructor.
     *
     * @param PdfParser $parser
     * @param PdfStream $stream
     */
    public function __construct(PdfParser $parser, PdfStream $stream)
    {
        $this->stream = $stream;
        $this->dict = $stream->value;

        if (isset($this->dict->value['Index'])) {
            // Read [Start Count] pairs
            $index = PdfArray::ensure($this->dict->value['Index']);
            for ($i = 0, $n = count($index->value); $i < $n; $i += 2) {
                $start = PdfNumeric::ensure($index->value[$i])->value;
                $count = PdfNumeric::ensure($index->value[$i + 1])->value;
                $this->subsections[$start] = $count;
            }
        } else {
            // Default value is [0 Size]
            $this->subsections[0] = $this->dict->value['Size']->value;
        }

        $fieldSizes = PdfDictionary::get($this->dict, 'W', new PdfArray())->value;
        foreach ($fieldSizes as $fieldSize) {
            $this->fieldSizes[] = PdfNumeric::ensure($fieldSize)->value;
        }

        $this->fieldsSize = array_sum($this->fieldSizes);
    }

    /**
     * Get an offset by an object number.
     *
     * @param int $objectNumber
     * @return int|bool False if the offset was not found.
     */
    public function getOffsetFor($objectNumber)
    {
        $streamOffset = 0;

        foreach ($this->subsections as $start => $count) {
            if ($objectNumber < $start || $objectNumber >= ($start + $count)) {
                $streamOffset += ($this->fieldsSize * $count);
                continue;
            }

            $streamOffset += ($this->fieldsSize * ($objectNumber - $start));
            $this->resetReader($streamOffset);

            $entryType = $this->readField($this->fieldSizes[0]);

            switch ($entryType) {
                case 1:
                    $offset = $this->readField($this->fieldSizes[1]);
                    return $offset;

                case 2:
                    $stream = $this->readField($this->fieldSizes[1]);
                    $object = $this->readField($this->fieldSizes[2]);
                    return array($stream, $object);

                default:
                    return false;
            }
        }

        return false;
    }

    private function resetReader($streamOffset) {
        if ($this->streamReader === null) {
            $this->streamReader = StreamReader::createByString($this->stream->getUnfilteredStream());
        }
        $this->streamReader->reset($streamOffset);
    }

    private function readField($fieldSize) {
        $value = 0;

        for ($n = 0; $n < $fieldSize; $n++) {
            $value = ($value << 8) + (ord($this->streamReader->readByte()) & 0xff);
        }

        return $value;
    }

    /**
     * Get the trailer related to this cross reference.
     *
     * @return PdfDictionary
     */
    public function getTrailer()
    {
        return $this->dict;
    }
}
