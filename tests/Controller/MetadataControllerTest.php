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
        $this->loginAsUser($this->user);

        \OC\Files\Filesystem::tearDown();
        \OC\Files\Filesystem::mount('\OC\Files\Storage\Local', array('datadir' => realpath(__DIR__ . '/../files')), '/' . $this->user . '/files');
        \OC\Files\Filesystem::init($this->user, '/' . $this->user . '/files');

        $this->controller = new MetadataController(
            'metadata',
            $this->createMock(\OCP\IRequest::class)
        );
    }

    public function testTxt() {
        $res = $this->controller->get('a.txt');
        $data = $res->getData();
        $this->assertEquals('error', $data['response']);
    }

    public function testJpg() {
        $res = $this->controller->get('IMG_20170626_181110.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('2017-06-26 18:11:09', $metadata['Date taken']);
        $this->assertEquals('4032 x 3016', $metadata['Dimensions']);
        $this->assertEquals('Xiaomi MI 6', $metadata['Camera used']);
        $this->assertEquals('sagit-user 7.1.1 NMF26X V8.2.2.0.NCAMIEC release-keys', $metadata['Software']);
        $this->assertEquals('f/1.8', $metadata['F-stop']);
        $this->assertEquals('1/649 sec.', $metadata['Exposure time']);
        $this->assertEquals('ISO-100', $metadata['ISO speed']);
        $this->assertEquals('3.82 mm', $metadata['Focal length']);
        $this->assertEquals('Center Weighted Average', $metadata['Metering mode']);
        $this->assertEquals('No flash, compulsory', $metadata['Flash mode']);
        $this->assertEquals('27', $metadata['35mm focal length']);
        $this->assertEquals('N 51° 31\' 31.5836"', $metadata['GPS latitude']);
        $this->assertEquals('W 0° 9\' 34.0459"', $metadata['GPS longitude']);
    }

    public function testMp3() {
        $res = $this->controller->get('sample_id3v1_id3v23.mp3');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('44100 Hz', $metadata['Sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
    }

    public function testOgg() {
        $res = $this->controller->get('sample.ogg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('44100 Hz', $metadata['Sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
    }

    public function testFlac() {
        $res = $this->controller->get('sample.flac');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('44100 Hz', $metadata['Sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
    }
}
