<?php
namespace OCA\Metadata\Controller;

use OC\Files\Filesystem;
use OCA\Metadata\AppInfo\Application;
use OCA\Metadata\Service\MetadataService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class MetadataController extends Controller {
	protected $userHome;
	protected $language;
	protected $metadataService;

	public function __construct($appName, IRequest $request, IUserSession $userSession, IRootFolder $rootFolder, IFactory $languageFactory, MetadataService $metadataService) {
		parent::__construct($appName, $request);

		$this->userHome = $rootFolder->getUserFolder($userSession->getUser()->getUID());
		$this->language = $languageFactory->get(Application::APP_ID);
		$this->metadataService = $metadataService;
	}

	/**
	 * @NoAdminRequired
	 */
	public function get($source) {
		try {
			$file = $this->userHome->get($source);
			$metadata = $this->metadataService->getMetadata($file);

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
						'msg' => $this->language->t('No metadata found.')
					)
				);
			}

		} catch (\Exception $e) {
			\OCP\Server::get(LoggerInterface::class)->error($e->getMessage(), ['app' => 'metadata']);

			return new JSONResponse(
				array(
					'response' => 'error',
					'msg' => $e->getMessage()
				)
			);
		}
	}
}
