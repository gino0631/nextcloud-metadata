<?php
namespace OCA\Metadata\Listener;

use OCA\Metadata\AppInfo\Application;
use OCA\Files\Event\LoadSidebar;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadSidebarListener implements IEventListener {
	public function handle(Event $event): void {
		if ($event instanceof LoadSidebar) {
			Util::addStyle(Application::APP_ID, 'tabview');
			Util::addScript(Application::APP_ID, 'tabview');

			$policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
			$policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
			$policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
			\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
		}
	}
}
