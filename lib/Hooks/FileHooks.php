<?php

namespace OCA\Metadata\Hooks;

use OCA\Metadata\Service\MetadataService;

class FileHooks {

	public function onNewFile(array $params) {
		$logger = \OC::$server->getLogger();
        $logger->error("FUCKINNN FILE!!!!!!!!!!!");
        $logger->error($params['path']);
        $metadataService = new MetadataService();
        $metadata = $metadataService->getMetadata($params['path']);
        $logger->error(json_encode($metadata->getArray()));
	}

}
