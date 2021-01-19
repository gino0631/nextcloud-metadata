<?php
namespace OCA\Metadata\Service;

class AcdsCategories {
    const EL_CATEGORY = 'Category';

    private $parser;
    private $text;
    private $data = array();
    private $path = array();
    private $assigned = '';

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
        $obj = new AcdsCategories();
        xml_parse($obj->parser, $xml, true);

        return $obj;
    }

    public function getArray() {
        return $this->data;
    }

    public function startElement($parser, $name, array $attributes) {
        if ($name === self::EL_CATEGORY) {
            $this->handleCurrent();
            $this->text = null;
            $this->assigned = $attributes['Assigned'];
        }
    }

    public function endElement($parser, $name) {
        if ($name === self::EL_CATEGORY) {
            $this->handleCurrent();
            $this->text = null;
            $this->assigned = '';
            array_pop($this->path);
        }
    }

    public function charData($parser, $data) {
        $this->text .= $data;
    }

    protected function handleCurrent() {
        if ($this->text) {
            array_push($this->path, $this->text);

            if ($this->assigned === '1') {
                array_push($this->data, implode('/', $this->path));
            }
        }
    }
}
