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
        \OC\Files\Filesystem::clearMounts();

        $this->controller = new MetadataController(
            'metadata',
            $this->createMock(\OCP\IRequest::class)
        );
    }

    public function testGet() {
        $res = $this->controller->get('a.txt');
        $data = $res->getData();
        $this->assertEquals('error', $data['response']);
        $this->assertEquals('File not found.', $data['msg']);
    }
}
