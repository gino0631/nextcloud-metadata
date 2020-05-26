<?php
namespace OCA\Metadata\AppInfo;

\OCP\App::registerPersonal('metadata', 'personal');

$app = \OC::$server->query(Application::class);
