<?php
namespace OCA\Metadata\AppInfo;

use OCA\Files\Event\LoadSidebar;
use OCA\Metadata\Listener\LoadSidebarListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	const APP_ID = 'metadata';

	/**
	 * Application constructor.
	 *
	 * @param array $params
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_ID, $params);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
	}

	public function boot(IBootContext $context): void {
	}

	public static function getL10N() {
		return \OC::$server->getL10N(Application::APP_ID);
	}
}
