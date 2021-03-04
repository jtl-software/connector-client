<?php
/**
 * @author Immanuel Klinkenberg <immanuel.klinkenberg@jtl-software.com>
 * @copyright 2010-2019 JTL-Software GmbH
 *
 * Created at 19.08.2019 09:45
 */

namespace jtl\Connector\Client;

use Jtl\Connector\Client\ConnectorClient;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\ProductImage;
use Jtl\UnitTest\TestCase;

class ConnectorClientTest extends TestCase
{
    protected $baseDir;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        $this->baseDir = dirname(__DIR__);
        parent::__construct($name, $data, $dataName);
    }

    public function testCreateZipFile()
    {
        $client = $this->createMock(ConnectorClient::class);

        $rows = [
            ['id' => new Identity('', 42), 'filename' => sprintf('%s/fixtures/testimage1.jpg', $this->baseDir)],
            ['id' => new Identity('', 77), 'filename' => sprintf('%s/fixtures/testimage2.png', $this->baseDir)],
            ['id' => new Identity('', 123), 'filename' => sprintf('%s/fixtures/testimage3.jpg', $this->baseDir)],
        ];

        $images = array_map(function(array $row) {
            $image = new ProductImage();
            foreach($row as $property => $value) {
                $setter = sprintf('set%s', ucfirst($property));
                $image->{$setter}($value);
            }
            return $image;
        }, $rows);

        $zipFile = $this->invokeMethodFromObject($client, 'createZipFile', ...$images);

        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($zipFile));
        $this->assertIsInt($zip->locateName('42_product_testimage1.jpg'));
        $this->assertIsInt($zip->locateName('77_product_testimage2.png'));
        $this->assertIsInt($zip->locateName('123_product_testimage3.jpg'));
    }
}
