<?php
namespace OCA\Metadata\Tests\Controller;

use OCA\Metadata\Controller\MetadataController;
use OCA\Metadata\Service\MetadataService;
use OCP\IUserManager;
use OCP\Server;
use Test\TestCase;

/**
 * @group DB
 */
class MetadataControllerTest extends TestCase {
    const EMSP = "\xE2\x80\x83";
    const BLACK_STAR = "\xE2\x98\x85";
    const WHITE_STAR = "\xE2\x98\x86";

    private $user;
    private $controller;

    public function setUp(): void {
        parent::setUp();

        $userBackend = new \Test\Util\User\Dummy();
        Server::get(IUserManager::class)->registerBackend($userBackend);

        $this->user = $this->getUniqueID('user_');
        $userBackend->createUser($this->user, '');

        $this->loginAsUser($this->user);

        \OC\Files\Filesystem::tearDown();
        \OC\Files\Filesystem::mount('\OC\Files\Storage\Local', array('datadir' => realpath(__DIR__ . '/../files')), '/' . $this->user . '/files');
        \OC\Files\Filesystem::init($this->user, '/' . $this->user . '/files');

        $this->controller = new MetadataController(
            'metadata',
            $this->createMock(\OCP\IRequest::class),
            new MetadataService()
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
        $this->assertEquals('WinTitle', $metadata['Title']);
        $this->assertEquals('WinSubject', $metadata['Description']);
        $this->assertEquals('2017-06-26 18:11:09', $metadata['Date created']);
        $this->assertEquals('4032 x 3016', $metadata['Dimensions']);
        $this->assertEquals('Xiaomi MI 6', $metadata['Camera used']);
        $this->assertEquals('sagit-user 7.1.1 NMF26X V8.2.2.0.NCAMIEC release-keys', $metadata['Software']);
        $this->assertEquals('1/649 sec.' . self::EMSP . 'f/1.8' . self::EMSP . 'ISO-100', $metadata['Exposure']);
        $this->assertEquals('3.82 mm (35 mm equivalent: 27 mm)', $metadata['Focal length']);
        $this->assertEquals('Center Weighted Average', $metadata['Metering mode']);
        $this->assertEquals('No flash, compulsory', $metadata['Flash mode']);
        $this->assertEquals('N 51° 31\' 31.58"' . self::EMSP . 'W 0° 9\' 34.05"', $metadata['GPS coordinates']);
        $this->assertEquals('0 m', $metadata['GPS altitude']);
    }

    public function testJpgXmp() {
        $res = $this->controller->get('IMG_20170626_181110_XMP.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Roses', $metadata['Title']);
        $this->assertEquals('Yellow roses in a park', $metadata['Description']);
        $this->assertEquals('Beautiful yellow roses in a park', $metadata['Comment']);
        $this->assertEquals(['Rose2', 'Rose1'], $metadata['People']);
        $this->assertEquals(['Rose/Rose1', 'Rose/Rose2'], $metadata['Tags']);
        $this->assertEquals(['Rose', 'Rose1', 'Rose2'], $metadata['Keywords']);
        $this->assertEquals(self::BLACK_STAR . self::BLACK_STAR . self::BLACK_STAR . self::WHITE_STAR . self::WHITE_STAR, $metadata['Rating']);
        $this->assertEquals('2017-06-26 18:11:09', $metadata['Date created']);
        $this->assertEquals('96 x 128', $metadata['Dimensions']);
        $this->assertEquals('Xiaomi MI 6', $metadata['Camera used']);
        $this->assertEquals('1/649 sec.' . self::EMSP . 'f/1.8' . self::EMSP . 'ISO-100', $metadata['Exposure']);
        $this->assertEquals('3.82 mm (35 mm equivalent: 27 mm)', $metadata['Focal length']);
        $this->assertEquals('Center Weighted Average', $metadata['Metering mode']);
        $this->assertEquals('No flash, compulsory', $metadata['Flash mode']);
        $this->assertEquals('N 51° 31\' 31.58"' . self::EMSP . 'W 0° 9\' 34.05"', $metadata['GPS coordinates']);
        $this->assertEquals('0 m', $metadata['GPS altitude']);
    }

    public function testTifXmp() {
        $res = $this->controller->get('IMG_20170626_181110_XMP.tif');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Roses', $metadata['Title']);
        $this->assertEquals('Yellow roses in a park', $metadata['Description']);
        $this->assertEquals('Beautiful yellow roses in a park', $metadata['Comment']);
        $this->assertEquals(['Rose2', 'Rose1'], $metadata['People']);
        $this->assertEquals(['Rose/Rose1', 'Rose/Rose2'], $metadata['Tags']);
        $this->assertEquals(['Rose', 'Rose1', 'Rose2'], $metadata['Keywords']);
        $this->assertEquals(self::BLACK_STAR . self::BLACK_STAR . self::BLACK_STAR . self::WHITE_STAR . self::WHITE_STAR, $metadata['Rating']);
        $this->assertEquals('2017-06-26 18:11:09', $metadata['Date created']);
        $this->assertEquals('96 x 128', $metadata['Dimensions']);
        $this->assertEquals('Xiaomi MI 6', $metadata['Camera used']);
        $this->assertEquals('1/649 sec.' . self::EMSP . 'f/1.8' . self::EMSP . 'ISO-100', $metadata['Exposure']);
        $this->assertEquals('3.82 mm (35 mm equivalent: 27 mm)', $metadata['Focal length']);
        $this->assertEquals('Center Weighted Average', $metadata['Metering mode']);
        $this->assertEquals('No flash, compulsory', $metadata['Flash mode']);
        $this->assertEquals('N 51° 31\' 31.58"' . self::EMSP . 'W 0° 9\' 34.05"', $metadata['GPS coordinates']);
        $this->assertEquals('0 m', $metadata['GPS altitude']);
    }

    public function testRawXmp() {
        $res = $this->controller->get('RAW_KODAK_DC50.KDC');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('DC50 Camera V1.1', $metadata['Software']);
        $this->assertEquals(['Douro', 'Holiday', 'Location', 'Occasion', 'Palácio de Cristal', 'Porto'], $metadata['Keywords']);
        $this->assertEquals('2018-10-06 13:20:38', $metadata['Date created']);
    }

    public function testHeic() {
        $res = $this->controller->get('IMG_20170626_181110.heic');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('2017-06-26 18:11:09', $metadata['Date created']);
        $this->assertEquals('4032 x 3016', $metadata['Dimensions']);
        $this->assertEquals('Xiaomi MI 6', $metadata['Camera used']);
        $this->assertEquals('sagit-user 7.1.1 NMF26X V8.2.2.0.NCAMIEC release-keys', $metadata['Software']);
        $this->assertEquals('1/649 sec.' . self::EMSP . 'f/1.8' . self::EMSP . 'ISO-100', $metadata['Exposure']);
        $this->assertEquals('3.82 mm (35 mm equivalent: 27 mm)', $metadata['Focal length']);
        $this->assertEquals('Center Weighted Average', $metadata['Metering mode']);
        $this->assertEquals('No flash, compulsory', $metadata['Flash mode']);
        $this->assertEquals('N 51° 31\' 31.58"' . self::EMSP . 'W 0° 9\' 34.05"', $metadata['GPS coordinates']);
        $this->assertEquals('0 m', $metadata['GPS altitude']);
    }

    public function testJpgIptc() {
        $res = $this->controller->get('iptc.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Testing IPTC Object Names', $metadata['Title']);
        $this->assertEquals('This is a headline', $metadata['Headline']);
        $this->assertEquals(['album:Normandy SR', 'game:Mass Effect 2'], $metadata['Keywords']);
        $this->assertEquals('This is a byline', $metadata['Author']);
        $this->assertEquals('This is a byline title', $metadata['Job title']);
        $this->assertEquals('This is a credit', $metadata['Credits']);
    }

    public function testJpgAcdsee() {
        $res = $this->controller->get('acdsee.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals("Feuerwehr Kesternich  Schlauchpflege\nv.l.: Jakob Krings (Köbes), Winfried Stollenwerk (Winnes)", $metadata['Description']);
        $this->assertEquals(['Personen', 'Vereine/Feuerwehr'], $metadata['Tags']);
    }

    public function testJpgUnicode() {
        $res = $this->controller->get('viagem.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Viágem', $metadata['Title']);
        $this->assertEquals('Viágem', $metadata['Description']);
        $this->assertEquals('Viágem', $metadata['Comment']);
        $this->assertEquals('Viágem', $metadata['Keywords']);
        $this->assertEquals('Viágem', $metadata['Author']);
        $this->assertEquals('Viágem', $metadata['Copyright']);
        $this->assertEquals('Viágem', $metadata['Camera used']);
    }

    public function testJpgGps() {
        $res = $this->controller->get('canon.jpg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Canon EOS 600D', $metadata['Camera used']);
        $this->assertEquals('N 46° 46\' 52.51"' . self::EMSP . 'E 15° 30\' 51.93"', $metadata['GPS coordinates']);
        $this->assertEquals('415 m', $metadata['GPS altitude']);
    }

    public function testPng() {
        $res = $this->controller->get('sample.png');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('1 x 1', $metadata['Dimensions']);
        $this->assertEquals('Short (one line) title or caption for image', $metadata['Title']);
        $this->assertEquals('Name of image\'s creator', $metadata['Author']);
        $this->assertEquals('Description of image (possibly long)', $metadata['Description']);
        $this->assertEquals('Copyright notice', $metadata['Copyright']);
        $this->assertEquals('Time of original image creation', $metadata['Date created']);
        $this->assertEquals('Software used to create the image', $metadata['Software']);
        $this->assertEquals('Legal disclaimer', $metadata['Disclaimer']);
        $this->assertEquals('Warning of nature of content', $metadata['Warning']);
        $this->assertEquals('Device used to create the image', $metadata['Source']);
        $this->assertEquals('Miscellaneous comment; conversion from GIF comment', $metadata['Comment']);
        $this->assertEquals("Information on AI-generated images is often contained in 'parameters'.\nBut this is not a predefined keyword.", $metadata['parameters']);
        $this->assertEquals('This is tEXt chunks.', $metadata['Text1']);
        $this->assertEquals('This is zTxt chunks. This content is compressed.', $metadata['Test2']);
        $this->assertEquals('This is tTXt chunks. Contains ja_JP translation keyword.', $metadata['Test3']);
    }

    public function testMp3() {
        $res = $this->controller->get('sample_id3v1_id3v23.mp3');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('LAME', $metadata['Audio codec']);
        $this->assertEquals('2', $metadata['Audio channels']);
        $this->assertEquals('44.1 kHz', $metadata['Audio sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
        $this->assertEquals('ENCODER234567890123456789012345', $metadata['Encoded by']);
        $this->assertEquals('LAME3.92', $metadata['Encoding tool']);
    }

    public function testOgg() {
        $res = $this->controller->get('sample.ogg');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('2', $metadata['Audio channels']);
        $this->assertEquals('44.1 kHz', $metadata['Audio sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
        $this->assertEquals('ENCODER234567890123456789012345', $metadata['Encoded by']);
        $this->assertEquals('Lavf57.73.100', $metadata['Encoding tool']);
    }

    public function testFlac() {
        $res = $this->controller->get('sample.flac');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('2', $metadata['Audio channels']);
        $this->assertEquals('44.1 kHz', $metadata['Audio sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
        $this->assertEquals('ENCODER234567890123456789012345', $metadata['Encoded by']);
        $this->assertEquals('Lavf57.76.100', $metadata['Encoding tool']);
    }

    public function testWav() {
        $res = $this->controller->get('sample.wav');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('ARTIST123456789012345678901234', $metadata['Artist']);
        $this->assertEquals('TITLE1234567890123456789012345', $metadata['Title']);
        $this->assertEquals('00:00:00', $metadata['Length']);
        $this->assertEquals('Pulse Code Modulation (PCM)', $metadata['Audio codec']);
        $this->assertEquals('2', $metadata['Audio channels']);
        $this->assertEquals('44.1 kHz', $metadata['Audio sample rate']);
        $this->assertEquals('ALBUM1234567890123456789012345', $metadata['Album']);
        $this->assertEquals('1', $metadata['Track #']);
        $this->assertEquals('2001', $metadata['Year']);
        $this->assertEquals('Pop', $metadata['Genre']);
        $this->assertEquals('COMMENT123456789012345678901', $metadata['Comment']);
        $this->assertEquals('ENCODER234567890123456789012345', $metadata['Encoded by']);
        $this->assertEquals('Lavf57.76.100', $metadata['Encoding tool']);
    }

    public function testPdf() {
        $res = $this->controller->get('sampleunsecuredpdf.pdf');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('Sample title', $metadata['Title']);
        $this->assertEquals('Sample author', $metadata['Author']);
        $this->assertEquals('Sample subject', $metadata['Subject']);
        $this->assertEquals('keyword1, keyword2', $metadata['Keywords']);
        $this->assertEquals('2012-03-30 11:25:26 +04:00', $metadata['Created']);
        $this->assertEquals('2025-10-06 12:09:05 +00:00', $metadata['Modified']);
        $this->assertEquals('Aspose Pty Ltd.', $metadata['Application']);
        $this->assertEquals('1234', $metadata['ISSN']);
        $this->assertEquals('Sample abstract', $metadata['Abstract']);
        $this->assertEquals('1', $metadata['Number of pages']);
        $this->assertEquals('No', $metadata['Trapped']);
        $this->assertEquals('Aspose.PDF for .NET 25.8.0', $metadata['PDF producer']);
        $this->assertEquals('1.3', $metadata['PDF version']);
    }

    public function testZip() {
        $res = $this->controller->get('sample.zip');
        $data = $res->getData();
        $this->assertEquals('success', $data['response']);

        $metadata = $data['metadata'];
        $this->assertEquals('3', $metadata['Number of files']);
        $this->assertEquals('Sample comment', $metadata['Comment']);
    }
}
