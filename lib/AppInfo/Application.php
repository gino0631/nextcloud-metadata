<?php

namespace OCA\Metadata\AppInfo;

use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;

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
		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $server->query(IEventDispatcher::class);

		$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
			\OCP\Util::addStyle('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'tabview' );
			\OCP\Util::addScript('metadata', 'plugin' );

			$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
			$policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
			$policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
			\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
		});
	}
}
