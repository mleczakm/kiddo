<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\EventSubscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
class MaliciousRequestSubscriberFunctionalTest extends WebTestCase
{
    /**
     * @return array<string, list<string>>
     */
    public static function maliciousPathsProvider(): array
    {
        return [
            'env file' => ['/.env'],
            'env with extension' => ['/.env.backup'],
            'env with production' => ['/.env.production'],
            'phpinfo' => ['/phpinfo'],
            'phpinfo.php' => ['/phpinfo.php'],
            'test.php' => ['/test.php'],
            'info.php' => ['/info.php'],
            'wordpress admin' => ['/wp-admin'],
            'wordpress content' => ['/wp-content'],
            'wordpress includes' => ['/wp-includes'],
            'aws credentials' => ['/.aws/credentials'],
            'aws config' => ['/.aws/config'],
            'vscode settings' => ['/.vscode/settings.json'],
            'sql dump' => ['/dump.sql'],
            'backup sql' => ['/backup.sql'],
            'backup config' => ['/backup_web_config.txt'],
            'sftp config' => ['/sftp-config.json'],
            'app dev' => ['/app_dev.php/something'],
        ];
    }

    #[DataProvider('maliciousPathsProvider')]
    public function testMaliciousPathsReturnZipBomb(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $response = $client->getResponse();

        // Should return 200 OK with zip content
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="backup.zip"', $response->headers->get('Content-Disposition'));

        // Response should contain zip signatures
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString("\x50\x4b\x03\x04", $content); // Local file header
        $this->assertStringContainsString("\x50\x4b\x01\x02", $content); // Central directory header
        $this->assertStringContainsString("\x50\x4b\x05\x06", $content); // End of central directory
    }

    /**
     * @return array<string, list<string>>
     */
    public static function legitimatePathsProvider(): array
    {
        return [
            'regular page' => ['/about'],
            'api endpoint' => ['/api/users'],
            'static asset' => ['/css/style.css'],
            'random path' => ['/some/random/path'],
        ];
    }

    #[DataProvider('legitimatePathsProvider')]
    public function testLegitimatePathsReturn404(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $response = $client->getResponse();

        // Should return 404 Not Found
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testPostToMaliciousPathAlsoReturnsZipBomb(): void
    {
        $client = static::createClient();
        $client->request('POST', '/wp-admin', [], [], [], '{"test": "data"}');

        $response = $client->getResponse();

        // Should return 200 OK with zip content
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    }
}
