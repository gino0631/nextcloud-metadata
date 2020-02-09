<?php
namespace OCA\Metadata\Controller;

use OC\Files\Filesystem;
use OCA\Metadata\AppInfo\Application;
use OCA\Metadata\Service\MetadataService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

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
			$metadata = $this->metadataService->getMetadata($source);

			if (!empty($metadata) && !$metadata->isEmpty()) {
				return new JSONResponse(
					array(
						'response' => 'success',
						'metadata' => $metadata->getArray(),
						'lat' => $metadata->getLat(),
						'lon' => $metadata->getLon(),
						'loc' => $metadata->getLoc()
					)
				);

			} else {
				return new JSONResponse(
					array(
						'response' => 'error',
						'msg' => Application::getL10N()->t('No metadata found.')
					)
				);
			}

		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'metadata']);

			return new JSONResponse(
				array(
					'response' => 'error',
					'msg' => $e->getMessage()
				)
			);
		}
	}
}
