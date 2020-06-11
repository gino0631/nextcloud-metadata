<?php
namespace OCA\Metadata\Cron;

use \OCA\Metadata\Controller\MetadataController;
use \OC\BackgroundJob\QueuedJob;
use \OCP\IUserManager;
use \OCP\ILogger;

class MetadataTagJob extends QueuedJob {

    public function __construct() {
		$logger = \OC::$server->getLogger();
        $this->logger = $logger;
		$this->userManager = \OC::$server->getUserManager();
        $this->filesystem = new \OC\Files\View('/' . 'tony');
        $metadataService = new MetadataService();
    }

    protected function run($arguments) {
        print("WTF!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
        /*
        $this->logger->error("ARGUMENTS: $arguments");
        $this->logger->error(json_encode(get_class_methods($this->userManager)));
        $users = $this->userManager->search('');
        $user = $users['tony'];
        $this->logger->error("UUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUUSER:");
        $this->logger->error(json_encode($users));
        $this->logger->error(json_encode(get_class_methods($user)));
         */
        $this->logger->error("FIIIIIIIIIIIIIIIIIILES:");
        $files = $this->filesystem->getDirectoryContent('/files');
        $this->logger->error(json_encode(get_class_methods($files[0])));
        $this->logger->error(json_encode($files[0]->getPath()));
        // TODO: extract metadata tag creation file hook into service and
        // recurse users, files and directories from here
    }

}
