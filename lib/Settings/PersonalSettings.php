<?php
namespace OCA\Metadata\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class PersonalSettings implements ISettings {

        /** @var IConfig */
        private $config;

        /**
         * Admin constructor.
         *
         * @param IConfig $config
         */
        public function __construct(IConfig $config) {
                $this->config = $config;
        }

        /**
         * @return TemplateResponse
         */
        public function getForm() {
            // TODO: add Metadata and UserMetadata tables/migrations
            // TODO: fetch metatada->tags user settings
            // $lastSentReportTime = (int) $this->config->getAppValue('survey_client', 'last_sent', 0);

            $parameters = [
                'metadata_options' => ['foo', 'bar', 'baz'],
                'user_metadata_tags' => ['foo'],
                'migrate_existing' => false
            ];

            return new TemplateResponse('metadata', 'admin', $parameters);
        }

        /**
         * @return string the section ID, e.g. 'sharing'
         */
        /*
        public function getSection() {
                return 'survey_client';
        }
         */

        /**
         * @return int whether the form should be rather on the top or bottom of
         * the admin section. The forms are arranged in ascending order of the
         * priority values. It is required to return a value between 0 and 100.
         */
        /*
        public function getPriority() {
                return 50;
        }
         */

}
