<?php

namespace OCA\Metadata\Hooks;

use OCA\Metadata\Service\MetadataService;

class FileHooks {

	public function onNewFile(array $params) {
        /* TODO: (first iteration)
         * - test data structure for keys/options to use to write tags
         * - test tag writing
         * - only use hard-coded IPTC 'keys' (for now)
         * - extract tag writing to "service"
         * - trigger queued job to write existing tags on update/install?
         * - create personal settings branch
         * - push WIP branch and start working on feature to share tagged photos
         * (second iteration)
         * - persist personal settings to db table
         * - fetch db table/selected options for Hook
         * - trigger queued job if "write tags for existing files?" selected
         * - trigger job when new metadata options selected
         */

		$logger = \OC::$server->getLogger();
        $logger->error("FUCKINNN FILE!!!!!!!!!!!");
        $logger->error($params['path']);
        $metadataService = new MetadataService();
        $metadata = $metadataService->getMetadata($params['path'])->getArray();

        // convert IPTC keywords to tags
        foreach ($metadata['Keywords'] as $keyword) {
            // create system tag if doesn't exist and add systemtag object
            // mapping record based on file id and tag id
            // - OC\SystemTag\SystemTagManager::createTag
            // - OC\SystemTag\SystemTagObjectMapper::assignTags
        }
	}

}
