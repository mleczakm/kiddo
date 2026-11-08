<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards what Android (Chrome install criteria) and iOS (Add to Home Screen,
 * required before Safari exposes Web Push) need to treat Kiddo as an app.
 */
#[Group('functional')]
final class PwaInstallabilityTest extends WebTestCase
{
    private const PUBLIC_DIR = __DIR__ . '/../../public';

    public function testManifestDeclaresAnInstallableStandaloneApp(): void
    {
        $manifest = $this->manifest();

        static::assertSame('standalone', $manifest['display']);
        static::assertNotSame('', $manifest['name']);
        static::assertNotSame('', $manifest['short_name']);
        static::assertSame('/', $manifest['scope']);
        static::assertStringStartsWith('/', $manifest['start_url']);
        static::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['theme_color']);
        static::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $manifest['background_color']);
    }

    public function testManifestIconsCoverChromeInstallRequirements(): void
    {
        $sizes = [];
        $purposes = [];
        foreach ($this->manifest()['icons'] as $icon) {
            $file = self::PUBLIC_DIR . $icon['src'];
            static::assertFileExists($file, $icon['src']);

            $info = getimagesize($file);
            static::assertNotFalse($info);
            static::assertSame($icon['sizes'], $info[0] . 'x' . $info[1], $icon['src']);
            static::assertSame('image/png', $icon['type']);

            $sizes[] = $icon['sizes'];
            $purposes[] = $icon['purpose'];
        }

        static::assertContains('192x192', $sizes);
        static::assertContains('512x512', $sizes);
        static::assertContains('maskable', $purposes);
    }

    public function testServiceWorkerHandlesPushClicksAndIsInstallable(): void
    {
        $worker = file_get_contents(self::PUBLIC_DIR . '/sw.js');
        static::assertIsString($worker);

        foreach (['push', 'notificationclick', 'fetch'] as $event) {
            static::assertStringContainsString("addEventListener('{$event}'", $worker);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pageProvider(): iterable
    {
        yield 'public site' => ['/'];
        yield 'customer panel' => ['/panel'];
    }

    #[DataProvider('pageProvider')]
    public function testPagesLinkManifestAndAppleMeta(string $path): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('pwa-' . md5($path) . '@example.test')->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', $path);

        static::assertResponseIsSuccessful();
        static::assertSame('/manifest.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
        static::assertCount(1, $crawler->filter('meta[name="theme-color"]'));
        static::assertSame('yes', $crawler->filter('meta[name="apple-mobile-web-app-capable"]')->attr('content'));
        static::assertCount(1, $crawler->filter('link[rel="apple-touch-icon"]'));
        static::assertStringContainsString('width=device-width', (string) $crawler->filter('meta[name="viewport"]')->attr('content'));
    }

    /**
     * @return array{name: string, short_name: string, display: string, scope: string, start_url: string, theme_color: string, background_color: string, icons: list<array{src: string, sizes: string, type: string, purpose: string}>}
     */
    private function manifest(): array
    {
        $json = file_get_contents(self::PUBLIC_DIR . '/manifest.webmanifest');
        static::assertIsString($json);

        /** @var array{name: string, short_name: string, display: string, scope: string, start_url: string, theme_color: string, background_color: string, icons: list<array{src: string, sizes: string, type: string, purpose: string}>} */
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
