<?php

namespace OCA\Metadata\Hooks;

use OCA\Metadata\Service\MetadataTagService;

class FileHooks {

	public function onUpdatedFile(array $params) {
        /* TODO: (first iteration)
         * - trigger queued job to write existing tags on update/install?
         * - create personal settings branch
         * - push WIP branch and start working on feature to share tagged photos
         * (second iteration)
         * - persist personal settings to db table
         * - fetch db table/selected options for Hook
         * - trigger queued job if "write tags for existing files?" selected
         * - trigger job when new metadata options selected
         */

        $metadataTagService = new MetadataTagService();
        $tagIds = $metadataTagService->getOrCreateTags($params['path']);
        if ($tagIds) {
            $metadataTagService->assignTags($tagIds);
        }

	}

}
