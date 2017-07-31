<?php

\OC::$server->getEventDispatcher()->addListener('OCA\Files::loadAdditionalScripts', function(){
    \OCP\Util::addStyle('metadata', 'tabview' );
    \OCP\Util::addScript('metadata', 'tabview' );
    \OCP\Util::addScript('metadata', 'plugin' );

    $policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();
    $policy->addAllowedConnectDomain('https://nominatim.openstreetmap.org/');
    $policy->addAllowedFrameDomain('https://www.openstreetmap.org/');
    \OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy);
});
