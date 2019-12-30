<?php
namespace OCA\Metadata\Service;

class IptcMetadata {
    const MAP = array(
        '2#005' => 'title',
        '2#025' => 'subject',
        '2#040' => 'instructions',
        '2#055' => 'dateCreated',
        '2#080' => 'creator',
        '2#085' => 'authorsPosition',
        '2#090' => 'city',
        '2#095' => 'state',
        '2#101' => 'country',
        '2#105' => 'headline',
        '2#110' => 'credit',
        '2#115' => 'source',
        '2#116' => 'rights',
        '2#120' => 'description',
        '2#122' => 'captionWriter'
    );

    private $data = array();

    private function __construct($bin) {
        if ($iptc = iptcparse($bin)) {
            foreach (self::MAP as $k => $v) {
                if (array_key_exists($k, $iptc)) {
                    $this->data[$v] = $iptc[$k];
                }
            }
        }
    }

    public static function fromData($bin) {
        return new IptcMetadata($bin);
    }

    public function getArray() {
        return $this->data;
    }
}
