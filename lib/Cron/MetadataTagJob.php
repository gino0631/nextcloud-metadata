<?php
namespace OCA\Metadata\Cron;

use \OC\BackgroundJob\QueuedJob;

use OCA\Metadata\Service\MetadataTagService;


class MetadataTagJob extends QueuedJob {

    public function __construct() {
		$logger = \OC::$server->getLogger();
        $this->logger = $logger;
		$this->userManager = \OC::$server->getUserManager();
        $this->metadataTagService = new MetadataTagService();

        $this->logger->error(json_encode(get_class_methods($this->userManager)));
        $users = $this->userManager->search('');
        $this->logger->error(json_encode($users));
        $user = $users['tony'];
        $this->logger->error(json_encode(get_class_methods($user)));
        $this->logger->error($user->getHome());

        $Directory = new \RecursiveDirectoryIterator($user->getHome());
        $Iterator = new \RecursiveIteratorIterator($Directory);
        # TODO: reference canonical list of supported metadata extensions
        $this->files = new \RegexIterator($Iterator, '/^.+\.(jpe?g|png|gif|bmp)$/i', \RecursiveRegexIterator::GET_MATCH);

    }

    protected function run($arguments) {
        print("WTF!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
        $this->logger->error("FIIIIIIIIIIIIIIIIIILES:");
        foreach ($this->files as $file) {
            // $this->logger->error(json_encode($file));
            //$relativePath = $this->filesystem->getLocalFile($file[0]);
            $view = new \OC\Files\View($file[0]);
            /*
            $relativePath = $filesystem->getRelativePath($file[0]);
            $absolutePath = $filesystem->getAbsolutePath($file[0]);
            $this->logger->error("RELATIVE: $relativePath, ABSOLUTE: $absolutePath");
             */
            // $this->logger->error("ROOT: $view->getRoot($file[0]");
            // $this->logger->error(json_encode(get_class_methods($view)));
            // $this->logger->error(json_encode(get_class_methods($view)));
            $this->logger->error("BASEEEEEEEEEEEE:"); 
            $this->logger->error(basename($file[0]));
            $tagIds = $this->metadataTagService->getOrCreateTags('//' . basename($file[0]));
            if ($tagIds) {
                // test existing and new tags on new file
                $this->metadataTagService->assignTags($tagIds);
            }
        }
        // TODO: extract metadata tag creation file hook into service and
        // recurse users, files and directories from here
    }

}
