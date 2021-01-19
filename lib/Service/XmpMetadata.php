<?php
namespace OCA\Metadata\Service;

class XmpMetadata {
    const EL_MWG_RS_REGIONS = 'mwg-rs:Regions';
    const EL_MWG_RS_NAME = 'mwg-rs:Name';
    const EL_MWG_RS_TYPE = 'mwg-rs:Type';
    const EL_DIGIKAM_TAGS_LIST = 'digiKam:TagsList';
    const EL_PS_AUTHORS_POSITION = 'photoshop:AuthorsPosition';
    const EL_PS_CAPTION_WRITER = 'photoshop:CaptionWriter';
    const EL_PS_CITY = 'photoshop:City';
    const EL_PS_COUNTRY = 'photoshop:Country';
    const EL_PS_CREDIT = 'photoshop:Credit';
    const EL_PS_DATE_CREATED = 'photoshop:DateCreated';
    const EL_PS_HEADLINE = 'photoshop:Headline';
    const EL_PS_INSTRUCTIONS = 'photoshop:Instructions';
    const EL_PS_SOURCE = 'photoshop:Source';
    const EL_PS_STATE = 'photoshop:State';
    const EL_AS_CAPTION = 'acdsee:caption';
    const EL_AS_CATEGORIES = 'acdsee:categories';
    const EL_DC_CREATOR = 'dc:creator';
    const EL_DC_DESCRIPTION = 'dc:description';
    const EL_DC_RIGHTS = 'dc:rights';
    const EL_DC_SUBJECT = 'dc:subject';
    const EL_DC_TITLE = 'dc:title';
    const EL_RDF_DESCRIPTION = 'rdf:Description';
    const EL_RDF_LI = 'rdf:li';

    private $parser;
    private $text;
    private $data = array();
    private $context = array();
    private $rsName = null;
    private $rsType = null;

    private function __construct() {
        $this->parser = xml_parser_create('UTF-8');
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_element_handler($this->parser, array($this, 'startElement'), array($this, 'endElement'));
        xml_set_character_data_handler($this->parser, array($this, 'charData'));
    }

    public function __destruct() {
        if (is_resource($this->parser)) {
            xml_parser_free($this->parser);
        }
    }

    public static function fromData($xml) {
        $obj = new XmpMetadata();
        xml_parse($obj->parser, $xml, true);

        return $obj;
    }

    public static function fromFile($file) {
        if ($hnd = fopen($file, 'rb')) {
            try {
                $obj = new XmpMetadata();

                while (($data = fread($hnd, 8192))) {
                    xml_parse($obj->parser, $data);
                }

                xml_parse($obj->parser, '', true);

                return $obj;

            } finally {
                fclose($hnd);
            }
        }
    }

    public function getArray() {
        return $this->data;
    }

    public function startElement($parser, $name, array $attributes) {
        $this->text = null;

        switch ($name) {
            // Elements to remember
            case self::EL_MWG_RS_REGIONS:
            case self::EL_DIGIKAM_TAGS_LIST:
            case self::EL_DC_CREATOR:
            case self::EL_DC_DESCRIPTION:
            case self::EL_DC_RIGHTS:
            case self::EL_DC_SUBJECT:
            case self::EL_DC_TITLE:
                $this->contextPush($name);
                break;

            case self::EL_RDF_DESCRIPTION:
                switch ($this->contextPeek()) {
                    case self::EL_MWG_RS_REGIONS:
                        if (array_key_exists(self::EL_MWG_RS_NAME, $attributes)) {
                            $this->rsName = $attributes[self::EL_MWG_RS_NAME];
                        }
                        if (array_key_exists(self::EL_MWG_RS_TYPE, $attributes)) {
                            $this->rsType = $attributes[self::EL_MWG_RS_TYPE];
                        }
                        break;

                    case NULL:
                        $this->addValIfExists(self::EL_PS_AUTHORS_POSITION, $attributes);
                        $this->addValIfExists(self::EL_PS_CAPTION_WRITER, $attributes);
                        $this->addValIfExists(self::EL_PS_CITY, $attributes);
                        $this->addValIfExists(self::EL_PS_COUNTRY, $attributes);
                        $this->addValIfExists(self::EL_PS_CREDIT, $attributes);
                        $this->addValIfExists(self::EL_PS_DATE_CREATED, $attributes);
                        $this->addValIfExists(self::EL_PS_HEADLINE, $attributes);
                        $this->addValIfExists(self::EL_PS_INSTRUCTIONS, $attributes);
                        $this->addValIfExists(self::EL_PS_SOURCE, $attributes);
                        $this->addValIfExists(self::EL_PS_STATE, $attributes);
                }
                break;
        }
    }

    public function endElement($parser, $name) {
        if ($this->contextPeek() === $name) {
            $this->contextPop();
        }

        switch ($name) {
            case self::EL_MWG_RS_NAME:
                $this->rsName = $this->text;
                break;

            case self::EL_MWG_RS_TYPE:
                $this->rsType = $this->text;
                break;

            case self::EL_PS_AUTHORS_POSITION:
            case self::EL_PS_CAPTION_WRITER:
            case self::EL_PS_CITY:
            case self::EL_PS_COUNTRY:
            case self::EL_PS_CREDIT:
            case self::EL_PS_DATE_CREATED:
            case self::EL_PS_HEADLINE:
            case self::EL_PS_INSTRUCTIONS:
            case self::EL_PS_SOURCE:
            case self::EL_PS_STATE:
            case self::EL_AS_CAPTION:
                $this->addVal($this->formatKey($name), $this->text);
                break;

            case self::EL_AS_CATEGORIES:
                $categories = AcdsCategories::fromData($this->text)->getArray();
                $this->addVal($this->formatKey($name), $categories);
                break;

            case self::EL_RDF_LI:
                $parent = $this->contextPeek();     // memorized in startElement()

                switch ($parent) {
                    case self::EL_MWG_RS_REGIONS:
                        if ($this->rsType === 'Face') {
                            $this->addVal('people', $this->rsName);
                        }
                        $this->rsName = null;
                        $this->rsType = null;
                        break;

                    case self::EL_DIGIKAM_TAGS_LIST:
                        if (!empty($this->text)) {
                            $this->addHierVal('tags', $this->text);
                        }
                        break;

                    case self::EL_DC_CREATOR:
                    case self::EL_DC_DESCRIPTION:
                    case self::EL_DC_RIGHTS:
                    case self::EL_DC_SUBJECT:
                    case self::EL_DC_TITLE:
                        $this->addVal($this->formatKey($parent), $this->text);
                        break;
                }
                break;
        }
    }

    public function charData($parser, $data) {
        $this->text .= $data;
    }

    protected function addValIfExists($key, &$attributes) {
        if (array_key_exists($key, $attributes)) {
            $this->addVal($this->formatKey($key), $attributes[$key]);
        }
    }

    protected function addVal($key, &$value) {
        if (!empty($value)) {
            if (!array_key_exists($key, $this->data)) {
                $this->data[$key] = array($value);

            } else {
                $this->data[$key][] = $value;
            }
        }
    }

    protected function addHierVal($key, &$value) {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = array($value);

        } else {
            if ((($prevIdx = count($this->data[$key]) - 1) >= 0) && (($prevVal = $this->data[$key][$prevIdx]) === substr($value, 0, strlen($prevVal)))) {
                $this->data[$key][$prevIdx] = $value;   // replace parent

            } else {
                $this->data[$key][] = $value;
            }
        }
    }

    protected function formatKey($key) {
        $pos = strrpos($key, ':');
        if ($pos !== false) {
            $key = substr($key, $pos + 1);
        }

        return lcfirst($key);
    }

    protected function contextPush($var) {
        array_push($this->context, $var);
    }

    protected function contextPop() {
        return array_pop($this->context);
    }

    protected function contextPeek() {
        return empty($this->context) ? null : array_values(array_slice($this->context, -1))[0];
    }
}
