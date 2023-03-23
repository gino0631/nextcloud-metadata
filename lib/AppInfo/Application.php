<?php

namespace OCA\Metadata\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	const APP_NAME = 'metadata';

	/**
	 * Application constructor.
	 *
	 * @param array $params
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_NAME, $params);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();
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
	}

	public static function getL10N() {
		return \OC::$server->getL10N(Application::APP_NAME);
	}
}
