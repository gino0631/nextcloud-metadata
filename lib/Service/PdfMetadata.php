<?php
namespace OCA\Metadata\Service;

use OCA\Metadata\Fpdi\PdfParser\PdfParser;
use OCA\Metadata\Fpdi\PdfParser\StreamReader;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfDictionary;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfHexString;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfName;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfNumeric;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfString;
use OCA\Metadata\Fpdi\PdfParser\Type\PdfType;

class PdfMetadata {
	private const REPLACEMENT_CHAR = "\u{FFFD}";

	private $pdfVersion;
	private $pageCount;
	private $info = array();

	public static function fromFile($file) {
		if ($hnd = fopen($file, 'rb')) {
			try {
				$obj = new PdfMetadata();
				$obj->readPdf($hnd);

				return ($obj->pdfVersion) ? $obj : null;

			} finally {
				fclose($hnd);
			}
		}

		return null;
	}

	public function getPdfVersionString() {
		return join('.', $this->pdfVersion);
	}

	public function getPageCount() {
		return $this->pageCount;
	}

	public function getInfo() {
		return $this->info;
	}

	private function readPdf($hnd) {
		$parser = new PdfParser(new StreamReader($hnd));
		$this->pdfVersion = $parser->getPdfVersion();

		$catalog = $parser->getCatalog();
		$pages = PdfType::resolve(PdfDictionary::get($catalog, 'Pages'), $parser);
		$count = PdfType::resolve(PdfDictionary::get($pages, 'Count'), $parser);
		$this->pageCount = PdfNumeric::ensure($count)->value;

		$trailer = $parser->getCrossReference()->getTrailer();
		$info = PdfType::resolve(PdfDictionary::get($trailer, 'Info'), $parser);

		if ($info instanceof PdfDictionary) {
			foreach ($info->value as $key => $value) {
				$value = PdfType::resolve($value, $parser);

				switch (get_class($value)) {
					case PdfString::class:
						$value = PdfString::unescape($value->value);
						break;

					case PdfHexString::class:
						$value = hex2bin($value->value);
						break;

					case PdfName::class:
						$value = PdfName::unescape($value->value);
						break;

					default:
						$value = 'Unsupported ' . get_class($value) . ' value';
				}

				if ((substr($key, -4) === 'Date') && (substr($value, 0, 2) === 'D:')) {
					$value = self::decodeDate($value);

				} else {
					$value = self::decodeString($value);
				}

				$this->info[$key] = $value;
			}
		}
	}

	// Date format is (D:YYYYMMDDHHmmSSOHH'mm'), all parts after the year are optional.
	private static function decodeDate($value) {
		$value = substr($value, 2);
		$len = strlen($value);

		if ($len > 14) {
			$o = $value[14];

			if (($len > 20) && (($o === '+') || ($o === '-'))) {
				$value[17] = ':';
				$value = substr($value, 0, $len - 1);

			} else {
				$value = substr_replace($value, 'UTC', 14, 1);
			}
		}

		if ($len > 14) {
			$value = substr_replace($value, ' ', 14, 0);
		}

		if ($len > 12) {
			$value = substr_replace($value, ':', 12, 0);
		}

		if ($len > 10) {
			$value = substr_replace($value, ':', 10, 0);
		}

		if ($len > 8) {
			$value = substr_replace($value, ' ', 8, 0);
		}

		if ($len >= 8) {
			$value = substr_replace($value, '-', 6, 0);
			$value = substr_replace($value, '-', 4, 0);
		}

		return $value;
	}

	private static function decodeString($value) {
		static $table;
		$maybeBom = substr($value, 0, 2);

		if (($maybeBom === "\xFE\xFF") || ($maybeBom === "\xFF\xFE")) {
			return iconv('UTF-16', 'UTF-8', $value);

		} else {
			if ($table === null) {
				$table = new \SplFixedArray(256);
				for ($pos = 0; $pos < 256; $pos++) {
					$table[$pos] = mb_chr($pos);
				}
				$table[0x18] = "\u{02D8}"; //BREVE
				$table[0x19] = "\u{02C7}"; //CARON
				$table[0x1A] = "\u{02C6}"; //MODIFIER LETTER CIRCUMFLEX ACCENT
				$table[0x1B] = "\u{02D9}"; //DOT ABOVE
				$table[0x1C] = "\u{02DD}"; //DOUBLE ACUTE ACCENT
				$table[0x1D] = "\u{02DB}"; //OGONEK
				$table[0x1E] = "\u{02DA}"; //RING ABOVE
				$table[0x1F] = "\u{02DC}"; //SMALL TILDE
				$table[0x7F] = self::REPLACEMENT_CHAR; //undefined
				$table[0x80] = "\u{2022}"; //BULLET
				$table[0x81] = "\u{2020}"; //DAGGER
				$table[0x82] = "\u{2021}"; //DOUBLE DAGGER
				$table[0x83] = "\u{2026}"; //HORIZONTAL ELLIPSIS
				$table[0x84] = "\u{2014}"; //EM DASH
				$table[0x85] = "\u{2013}"; //EN DASH
				$table[0x86] = "\u{0192}"; //LATIN SMALL LETTER SCRIPT F
				$table[0x87] = "\u{2044}"; //FRACTION SLASH (solidus)
				$table[0x88] = "\u{2039}"; //SINGLE LEFT-POINTING ANGLE QUOTATION MARK
				$table[0x89] = "\u{203A}"; //SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
				$table[0x8A] = "\u{2212}"; //MINUS SIGN
				$table[0x8B] = "\u{2030}"; //PER MILLE SIGN
				$table[0x8C] = "\u{201E}"; //DOUBLE LOW-9 QUOTATION MARK (quotedblbase)
				$table[0x8D] = "\u{201C}"; //LEFT DOUBLE QUOTATION MARK (double quote left)
				$table[0x8E] = "\u{201D}"; //RIGHT DOUBLE QUOTATION MARK (quotedblright)
				$table[0x8F] = "\u{2018}"; //LEFT SINGLE QUOTATION MARK (quoteleft)
				$table[0x90] = "\u{2019}"; //RIGHT SINGLE QUOTATION MARK (quoteright)
				$table[0x91] = "\u{201A}"; //SINGLE LOW-9 QUOTATION MARK (quotesinglbase)
				$table[0x92] = "\u{2122}"; //TRADE MARK SIGN
				$table[0x93] = "\u{FB01}"; //LATIN SMALL LIGATURE FI
				$table[0x94] = "\u{FB02}"; //LATIN SMALL LIGATURE FL
				$table[0x95] = "\u{0141}"; //LATIN CAPITAL LETTER L WITH STROKE
				$table[0x96] = "\u{0152}"; //LATIN CAPITAL LIGATURE OE
				$table[0x97] = "\u{0160}"; //LATIN CAPITAL LETTER S WITH CARON
				$table[0x98] = "\u{0178}"; //LATIN CAPITAL LETTER Y WITH DIAERESIS
				$table[0x99] = "\u{017D}"; //LATIN CAPITAL LETTER Z WITH CARON
				$table[0x9A] = "\u{0131}"; //LATIN SMALL LETTER DOTLESS I
				$table[0x9B] = "\u{0142}"; //LATIN SMALL LETTER L WITH STROKE
				$table[0x9C] = "\u{0153}"; //LATIN SMALL LIGATURE OE
				$table[0x9D] = "\u{0161}"; //LATIN SMALL LETTER S WITH CARON
				$table[0x9E] = "\u{017E}"; //LATIN SMALL LETTER Z WITH CARON
				$table[0x9F] = self::REPLACEMENT_CHAR; //undefined
				$table[0xA0] = "\u{20AC}"; //EURO SIGN
				$table[0xAD] = self::REPLACEMENT_CHAR; //undefined
			}

			$result = '';
			for ($pos = 0, $len = strlen($value); $pos < $len; $pos++) {
				$result .= $table[ord($value[$pos])];
			}

			return $result;
		}
	}
}
