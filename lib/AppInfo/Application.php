<?php

namespace OCA\Metadata\AppInfo;

use OCP\AppFramework\App;
use OCP\IContainer;
use OCP\Files\Events\FileCacheUpdated;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\Metadata\Hooks\FileHooks;
use OCA\Metadata\Service\MetadataService;
use OCP\Util;

class Application extends App {
	const APP_NAME = 'metadata';

	/**
	 * Application constructor.
	 *
	 * @param array $params
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_NAME, $params);

		$container = $this->getContainer();
		$server = $container->getServer();
		$eventDispatcher = $server->getEventDispatcher();

		$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
			\OCP\Util::addStyle('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'plugin' );

			$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
			$policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
			$policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
			\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
		});
        $this->registerHooks();
	}

    public function registerHooks() {
		Util::connectHook('OC_Filesystem', 'post_create', FileHooks::class, 'onUpdatedFile');
		Util::connectHook('OC_Filesystem', 'post_update', FileHooks::class, 'onUpdatedFile');
    }

	public static function getL10N() {
		return \OC::$server->getL10N(Application::APP_NAME);
	}
}
