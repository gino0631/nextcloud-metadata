<?php
namespace OCA\Metadata\Cron;

// use \OCA\MyApp\Service\SomeService;
use \OCA\Metadata\Controller\MetadataController;
use \OCP\BackgroundJob\TimedJob;
use \OCP\IUserManager;
use \OCP\ILogger;

class MetadataTagJob extends TimedJob {

    private $metadataController;

	/** @var IUserManager */
	protected $userManager;

    public function __construct(ITimeFactory $time, MetadataController $metadataController, IUserManager $userManager, ILogger $logger) {
        parent::__construct($time);
        $this->metadataController = $metadataController;
        $this->userManager = $userManager;
        $this->logger = $logger;

        // Run once every 5 minutes
        //parent::setInterval(300);
        parent::setInterval(5);
    }

    public static function run($arguments) {
        // $this->metadataController->doCron($arguments['uid']);
        $user = $this->userManager->get($arguments['uid']);
        throw new \Exception("user is $user");
        print("WTF!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
        $this->logger->error("WTF DUDE!!!!");
    }

}
