<?php
namespace OCA\Metadata\AppInfo;

use OCP\AppFramework\App;
use OCA\Metadata;
use OCA\Metadata\Services\MetadataService;
use OCA\Metadata\Services\MetadataIndexingService;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	const APP_NAME = 'metadata';
	private $metadataService;
	private $metadataIndexingService;

	public function __construct(array $params = array()) {
		parent::__construct(self::APP_NAME, $params);

		$container = $this->getContainer();
		$this->metadataService = new MetadataService($container->getAppName());
		$this->metadataIndexingService = new MetadataIndexingService($this->metadataService);

		$container->registerService('MetadataService', function(IAppContainer $c) {
			return $this->metadataService;
		});
		$eventDispatcher = \OC::$server->getEventDispatcher();
		$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
			\OCP\Util::addStyle('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'plugin' );

			$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
			$policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
			$policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
			\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
		});
		$eventDispatcher->addListener('\OCA\Files_FullTextSearch::onFileIndexing', function(GenericEvent $e) {
			$this->metadataIndexingService->onFileIndexing($e);
		});
		$eventDispatcher->addListener('\OCA\Files_FullTextSearch::onSearchRequest', function(GenericEvent $e) {
			$this->metadataIndexingService->onSearchRequest($e);
		});
	}
}
