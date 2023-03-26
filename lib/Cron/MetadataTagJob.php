<?php
namespace OCA\Metadata\Cron;

use \OC\BackgroundJob\QueuedJob;


class MetadataTagJob extends QueuedJob {

    public function __construct() {
		$logger = \OC::$server->getLogger();
        $this->logger = $logger;
		$this->userManager = \OC::$server->getUserManager();
        $config = new \OC\Config('config/');
        $this->basePath = $config->getValue('datadirectory');
    }

    protected function run($arguments) {
        $users = $this->userManager->search('');
        foreach ($users as $user) {
            if (file_exists($user->getHome())) {
                $userId = $user->getUID();
                \OC_Util::tearDownFS();
                \OC_Util::setupFS($userId);
                $Directory = new \RecursiveDirectoryIterator($user->getHome());
                $Iterator = new \RecursiveIteratorIterator($Directory);
                # TODO: reference canonical list of supported metadata extensions
                $files = new \RegexIterator($Iterator, '/^.+\.(jpe?g|png|gif|bmp)$/i', \RecursiveRegexIterator::GET_MATCH);
                $metadataTagService = new \OCA\Metadata\Service\MetadataTagService;
                $this->logger->error("Creating and assigning tags for user $userId's files.");
                foreach ($files as $file) {
                    $userFilesPath = "$this->basePath/$userId/files/";
                    $relativePath = str_replace($userFilesPath, '', $file[0]);
                    $tagIds = $metadataTagService->getOrCreateTags('//' . $relativePath);
                    if ($tagIds) {
                        // TODO: test existing and new tags on new file
                        $metadataTagService->assignTags($tagIds);
                    }
                }
            }
        }
    }

}
