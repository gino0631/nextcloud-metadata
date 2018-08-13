<?php
namespace OCA\Metadata\Controller;

use OC\Files\Filesystem;
use OCA\Metadata\GetID3\getID3;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\Metadata\Services\MetadataService;

class MetadataController extends Controller {
    protected $metadataService;

    public function __construct($appName, IRequest $request, MetadataService $metadataService) {
        parent::__construct($appName, $request);
        $this->metadataService = $metadataService;
    }

    /**
     * @NoAdminRequired
     */
    public function get($source) {
        try {
            $root = \OC::$server->getUserFolder();
            $node = $root->get($source);
            $metadata = $this->metadataService->getMetadata($node);
            if(empty($metadata->metadataArray))
            {
                return new JSONResponse(
                    array(
                        'response' => 'error',
                        'msg' => $this->language->t('No metadata found.')
                    )
                );
            }
            return new JSONResponse(
                array(
                    'response' => 'success',
                    'metadata' => $metadata->metadataArray,
                    'lat' => $metadata->lat,
                    'lon' => $metadata->lon
                )
            );
        } catch (\Exception $ex) {
            return new JSONResponse(
                array(
                    'response' => 'error',
                    'msg' => $ex->getMessage()
                )
            );
        }
    }
}
