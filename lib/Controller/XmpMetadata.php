<?php
namespace OCA\Metadata\Controller;

class XmpMetadata {
    const EL_MWG_RS_REGIONS = 'mwg-rs:Regions';
    const EL_MWG_RS_NAME = 'mwg-rs:Name';
    const EL_MWG_RS_TYPE = 'mwg-rs:Type';
    const EL_DIGIKAM_TAGS_LIST = 'digiKam:TagsList';
    const EL_DC_TITLE = 'dc:title';
    const EL_DC_DESCRIPTION = 'dc:description';
    const EL_RDF_DESCRIPTION = 'rdf:Description';
    const EL_RDF_LI = 'rdf:li';

    private $parser;
    private $text;
    private $data = array();
    private $context = array();
    private $rsName = null;
    private $rsType = null;

    public function __construct($xml) {
        $this->parser = xml_parser_create('UTF-8');
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 0);
        xml_set_element_handler($this->parser, array($this, 'startElement'), array($this, 'endElement'));
        xml_set_character_data_handler($this->parser, array($this, 'charData'));

        xml_parse($this->parser, $xml, true);
    }

    public function __destruct() {
        if (is_resource($this->parser)) {
            xml_parser_free($this->parser);
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
            case self::EL_DC_TITLE:
            case self::EL_DC_DESCRIPTION:
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

            case self::EL_RDF_LI:
                switch ($this->contextPeek()) {     // memorized in startElement()
                    case self::EL_MWG_RS_REGIONS:
                        if (($this->rsType === 'Face') && !empty($this->rsName)) {
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

                    case self::EL_DC_TITLE:
                        if (!empty($this->text)) {
                            $this->addVal('title', $this->text);
                        }
                        break;

                    case self::EL_DC_DESCRIPTION:
                        if (!empty($this->text)) {
                            $this->addVal('description', $this->text);
                        }
                        break;
                }
                break;
        }
    }

    public function charData($parser, $data) {
        $this->text .= $data;
    }

    protected function addVal($key, &$value) {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = array($value);

        } else {
            $this->data[$key][] = $value;
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
