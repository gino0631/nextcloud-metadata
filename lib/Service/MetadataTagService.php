<?php
namespace OCA\Metadata\Service;

use OC\Files\Filesystem;
use OCP\SystemTag\TagNotFoundException;

use OCA\Metadata\Service\MetadataService;


class MetadataTagService {

    public function __construct() {
        $this->metadataService = new MetadataService();
        $this->filesystem = Filesystem::getView();
        $this->tagManager = \OC::$server->getSystemTagManager();
        $this->tagObjectMapper = \OC::$server->getSystemTagObjectMapper();
        $this->filePath = null;
	}

    public function getOrCreateTags($filePath) {
        $this->filePath = $filePath;
        $metadata = $this->metadataService->getMetadata($filePath)->getArray();

        // convert IPTC keywords to tags
        $tagIds = [];
        $keywords = array_key_exists('Keywords', $metadata) ? $metadata['Keywords'] : [];
        foreach ($keywords as $keyword) {
            try {
                $tag = $this->tagManager->getTag($keyword, True, True);
            } catch (TagNotFoundException $e) {
                $tag = $this->tagManager->createTag($keyword, True, True);
            }
            array_push($tagIds, $tag->getId());
        }

        return $tagIds;
    }

    public function assignTags($tagIds) {
        $fileInfo = $this->filesystem->getFileInfo($this->filePath);
        $fileId = $fileInfo->getId();
        // how are object types defined? fileInfo->getType returns 'file', not
        // 'files', but this will break Tag assignment
        $this->tagObjectMapper->assignTags($fileId, 'files', $tagIds);
    }

}

?>
