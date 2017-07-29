<?php
namespace OCA\Metadata\Controller;

use OCP\IRequest;
use Test\TestCase;

class MetadataControllerTest extends TestCase {
    private $controller;

    public function setUp() {
        parent::setUp();

        $this->controller = new MetadataController(
            'metadata',
            $this->createMock(IRequest::class)
        );
    }

    public function testGet() {
        $res = $this->controller->get('a.txt');
        $data = $res->getData();
        $this->assertEquals('error', $data['response']);
        $this->assertEquals('File not found.', $data['msg']
    }
}
