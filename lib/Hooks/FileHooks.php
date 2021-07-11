<?php

namespace OCA\Metadata\Hooks;

use OCA\Metadata\Service\MetadataTagService;

class FileHooks {

	public function onUpdatedFile(array $params) {
        $metadataTagService = new MetadataTagService();
        $tagIds = $metadataTagService->getOrCreateTags($params['path']);
        if ($tagIds) {
            $metadataTagService->assignTags($tagIds);
        }
	}

}
