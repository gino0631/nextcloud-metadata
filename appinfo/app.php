<?php

\OC::$server->getEventDispatcher()->addListener('OCA\Files::loadAdditionalScripts', function(){
    \OCP\Util::addStyle('metadata', 'tabview' );
    \OCP\Util::addScript('metadata', 'tabview' );
    \OCP\Util::addScript('metadata', 'plugin' );
});
