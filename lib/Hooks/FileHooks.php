<?php

namespace OCA\Metadata\Hooks;

use OCA\Metadata\Service\MetadataService;

class FileHooks {

	public function onNewFile(array $params) {
        /* TODO: (first iteration)
         * - test file update hook
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
        $logger->error(json_encode($params));
        $metadataService = new MetadataService();
        $metadata = $metadataService->getMetadata($params['path'])->getArray();

        $view = \OC\Files\Filesystem::getView();
        $fileInfo = $view->getFileInfo($params['path']);
        $logger->error($fileInfo->getType());
        $fileId = $fileInfo->getId();
        $objectType = $fileInfo->getType();

        // convert IPTC keywords to tags
        $tagIds = [];
        foreach ($metadata['Keywords'] as $keyword) {
            $tagManager = \OC::$server->getSystemTagManager();
            $tag = $tagManager->getTag($keyword, True, True);
            if (!$tag) {
                $tag = $tagManager->createTag($keyword, True, True);
            }
            array_push($tagIds, $tag->getId());
        }
        $tagObjectMapper = \OC::$server->getSystemTagObjectMapper();
        // how are object types defined?
        $tagObjectMapper->assignTags($fileId, 'files', $tagIds);
	}

}
