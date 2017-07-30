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
        \OC_User::createUser($this->user, $this->user);

        \OC\Files\Filesystem::tearDown();
        \OC_User::setUserId($this->user);
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
