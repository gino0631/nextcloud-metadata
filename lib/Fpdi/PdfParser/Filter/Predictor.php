<?php

/**
 * @package   OCA\Metadata\Fpdi
 * @license   https://opensource.org/licenses/AGPL-3.0 GNU Affero General Public License version 3
 */

namespace OCA\Metadata\Fpdi\PdfParser\Filter;

class Predictor implements FilterInterface
{
    /**
     * @var int
     */
    protected $predictor;

    /**
     * @var int
     */
    protected $colors;

    /**
     * @var int
     */
    protected $bitsPerComponent;

    /**
     * @var int
     */
    protected $columns;

    /**
     * @param int $predictor
     * @param int $colors
     * @param int $bitsPerComponent
     * @param int $columns
     */
    public function __construct($predictor, $colors, $bitsPerComponent, $columns)
    {
        $this->predictor = $predictor;
        $this->colors = $colors;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->columns = $columns;
    }

    public function decode($data)
    {
        if ($this->predictor === 1) {
            return $data;
        }

        if (($this->predictor >= 10) && ($this->predictor <= 15)) {
            return $this->decodePng($data);
        }

        throw new FilterException(
            \sprintf('Unsupported predictor %s', $this->predictor),
            FilterException::NOT_IMPLEMENTED
        );
    }

    private function decodePng($data)
    {
        $dataLen = strlen($data);
        $bytesPerPixel = (int) \ceil($this->colors * $this->bitsPerComponent / 8);
        $bytesPerRow = (int) \ceil($this->colors * $this->bitsPerComponent * $this->columns / 8);
        $priorRow = \array_fill(0, $bytesPerRow, 0);
        $offset = 0;
        $result = '';

        while ($offset < $dataLen) {
            $predictor = \ord($data[$offset++]);

            $currRowLen = \min($bytesPerRow, $dataLen - $offset);
            $currRow = new \SplFixedArray($currRowLen);
            for ($i = 0; $i < $currRowLen; $i++) {
                $currRow[$i] = \ord($data[$offset++]);
            }

            switch ($predictor) {
                case 0:       // None
                    break;

                case 1:       // Sub
                    for ($i = $bytesPerPixel; $i < $currRowLen; $i++) {
                        $currRow[$i] = $currRow[$i] + $currRow[$i - $bytesPerPixel];
                    }
                    break;

                case 2:       // Up
                    for ($i = 0; $i < $currRowLen; $i++) {
                        $currRow[$i] = $currRow[$i] + $priorRow[$i];
                    }
                    break;

                case 3:       // Average
                    for ($i = 0; $i < $currRowLen; $i++) {
                        $left = ($i < $bytesPerPixel) ? 0 : $currRow[$i - $bytesPerPixel];
                        $currRow[$i] = $currRow[$i] + \floor(($left + $priorRow[$i]) / 2);
                    }
                    break;

                case 4:       // Paeth
//                    break;

                default:
                    throw new FilterException(
                        \sprintf('Unsupported predictor %s', $predictor),
                        FilterException::NOT_IMPLEMENTED
                    );
            }

            for ($i = 0; $i < $currRowLen; $i++) {
                $result .= \chr($currRow[$i]);
            }

            $priorRow = $currRow;
        }

        return $result;
    }
}
