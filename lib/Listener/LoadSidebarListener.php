<?php
namespace OCA\Metadata\Listener;

use OCA\Metadata\AppInfo\Application;
use OCA\Files\Event\LoadSidebar;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadSidebar|AddContentSecurityPolicyEvent>
 */
class LoadSidebarListener implements IEventListener {
	/**
	 * The container shares one instance for both events, so the policy is only
	 * added to responses of requests that load the sidebar.
	 */
	private bool $sidebarLoaded = false;

	public function handle(Event $event): void {
		if ($event instanceof LoadSidebar) {
			$this->sidebarLoaded = true;

			Util::addStyle(Application::APP_ID, 'tabview');
			Util::addScript(Application::APP_ID, 'tabview');

		} elseif (($event instanceof AddContentSecurityPolicyEvent) && $this->sidebarLoaded) {
			$policy = new EmptyContentSecurityPolicy();
			$policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
			$policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
			$event->addPolicy($policy);
		}
	}
}
