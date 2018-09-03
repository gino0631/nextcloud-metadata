<?php
namespace OCA\Metadata\Services;
use Symfony\Component\EventDispatcher\GenericEvent;
use OC\Files\Node\File;
use OCA\FullTextSearch\Model\SearchRequest;

class MetadataIndexingService {

    private $metadataService;

    public function __construct(MetadataService $metadataService) {
        $this->metadataService = $metadataService;
    }

    public function onSearchRequest(GenericEvent $e) {
        $request = $e->getArgument('request');
        if (!$request instanceof SearchRequest) {
            return;
        }
        foreach($request->getOption('meta') as $option) {
            $optionKvp = explode("=", $option, 2);
            $request->addSubTag(strtolower($optionKvp[0]), strtolower($optionKvp[1]));
        }
    }

    public function onFileIndexing(GenericEvent $e) {
        try {
            $file = $e->getArgument('file');
            if (!$file instanceof File) {
                return;
            }
            $document = $e->getArgument('document');

            $metadata = $this->metadataService->getMetadata($file)->metadataArray;
            $allTags = array();
            if (array_key_exists('Tags', $metadata)) {
                $allTags = array_merge($allTags, $metadata['Tags']);
            }
            if (array_key_exists('Keywords', $metadata)) {
                $allTags = array_merge($allTags, $metadata['Keywords']);
            }
            $subTags = [];
            foreach($allTags as $tag) {
                $lastPos = 0;
                while (($lastPos = strpos($tag, '/', $lastPos))!== false) {
                    $subTags[] = strtolower(substr($tag, 0, $lastPos));
                    $lastPos = $lastPos + 1;
                }
                $subTags[] = strtolower($tag);
            }
            $document->addSubTag("tag", $subTags);
        } catch (QueryException $qex) {
        } catch (\OCA\Metadata\Exceptions\UnsupportedFiletypeException $ex) {
        }
    }
}
