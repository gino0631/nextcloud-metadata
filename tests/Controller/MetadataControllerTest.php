<?php
namespace OCA\Metadata\Tests\Controller;

use OCA\Metadata\Controller\MetadataController;
use Test\TestCase;

class MetadataControllerTest extends TestCase {
    private $user;
    private $controller;

    public function setUp() {
        parent::setUp();

        $this->user = 'user_' . uniqid();
        $backend = new \Test\Util\User\Dummy();
        $backend->createUser($this->user, $this->user);
        \OC_User::useBackend($backend);

        \OC\Files\Filesystem::tearDown();
        \OC\Files\Filesystem::init($this->user, '/' . $this->user . '/files');
        \OC\Files\Filesystem::mount('\OC\Files\Storage\Local', array('datadir' => realpath(__DIR__ . '/../files')), 'test-data');
        //$this->putFile('IMG_20170626_181110.jpg');

        $this->controller = new MetadataController(
            'metadata',
            $this->createMock(\OCP\IRequest::class)
        );
    }

    protected function putFile($name) {
        \OC\Files\Filesystem::file_put_contents($name, file_get_contents(__DIR__ . '/../files/' . $name));
    }

    public function testGet() {
        $res = $this->controller->get('a.txt');
        $data = $res->getData();
//        $this->assertEquals('error', $data['response']);
//        $this->assertEquals('File not found.', $data['msg']);

        $res = $this->controller->get('test-data/IMG_20170626_181110.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);
    }
}
