<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
final class LegalDocumentActionTest extends WebTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function legalDocumentProvider(): array
    {
        return [
            'application terms' => ['/regulamin', 'Regulamin aplikacji Warsztatownia Sensoryczna'],
            'classes terms' => ['/regulamin-zajec', 'Regulamin zajęć Warsztatowni Sensorycznej (ogólny)'],
            'privacy policy' => ['/polityka-prywatnosci', 'Polityka prywatności Warsztatowni Sensorycznej'],
        ];
    }

    #[DataProvider('legalDocumentProvider')]
    public function testLegalDocumentIsPubliclyAvailable(string $path, string $heading): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $path);

        static::assertResponseIsSuccessful();
        static::assertSame($heading, $crawler->filter('main h1')->text());
        static::assertSelectorTextContains('main', 'Obowiązuje od 10 września 2026 r.');
    }

    public function testFooterLinksToEveryLegalDocument(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        static::assertResponseIsSuccessful();
        static::assertCount(1, $crawler->filter('footer a[href="/regulamin"]'));
        static::assertCount(1, $crawler->filter('footer a[href="/regulamin-zajec"]'));
        static::assertCount(1, $crawler->filter('footer a[href="/polityka-prywatnosci"]'));
    }
}
