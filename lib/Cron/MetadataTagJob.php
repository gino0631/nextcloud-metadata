<?php
namespace OCA\Metadata\Cron;

use \OC\BackgroundJob\QueuedJob;


class MetadataTagJob extends QueuedJob {

    public function __construct() {
		$logger = \OC::$server->getLogger();
        $this->logger = $logger;
		$this->userManager = \OC::$server->getUserManager();

        $this->logger->error(json_encode(get_class_methods($this->userManager)));
        $users = $this->userManager->search('');
        $this->logger->error(json_encode($users));
        $user = $users['tony'];

        // TODO: pass in files as associative array of files and users
        //
        //
        $this->user = $user;
        $this->logger->error(json_encode(get_class_methods($user)));
        $this->logger->error($user->getHome());

        $Directory = new \RecursiveDirectoryIterator($user->getHome());
        $Iterator = new \RecursiveIteratorIterator($Directory);
        # TODO: reference canonical list of supported metadata extensions
        $this->files = new \RegexIterator($Iterator, '/^.+\.(jpe?g|png|gif|bmp)$/i', \RecursiveRegexIterator::GET_MATCH);

    }

    protected function run($arguments) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($this->user->getUID());
        $metadataTagService = new \OCA\Metadata\Service\MetadataTagService;
        foreach ($this->files as $file) {
            $view = \OC\Files\Filesystem::getView();
            $this->logger->error("RELATIVE: $file[0]");
            $config = new \OC\Config('config/');
            $base_path = $config->getValue('datadirectory');
            $relative_path = str_replace($base_path . '/tony/files/', '', $file[0]);
            $this->logger->error("DATADIR: $base_path");
            $this->logger->error("BASEEEEEEEEEEEE:");
            $this->logger->error(basename($file[0]));
            $tagIds = $metadataTagService->getOrCreateTags('//' . $relative_path);
            if ($tagIds) {
                // test existing and new tags on new file
                $metadataTagService->assignTags($tagIds);
            }
        }
        // TODO: extract metadata tag creation file hook into service and
        // recurse users, files and directories from here
    }

	protected function setupFS($user) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user);

		// Check if this user has a trashbin directory
		$view = new \OC\Files\View('/' . $user);

		return true;
	}

}
