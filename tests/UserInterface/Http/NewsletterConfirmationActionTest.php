<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class NewsletterConfirmationActionTest extends WebTestCase
{
    public function testPolishRouteRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/newsletter-potwierdzony');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Zapis potwierdzony!', (string) $client->getResponse()->getContent());
    }

    public function testEnglishRouteRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/newsletter-confirmed');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Subscription confirmed!', (string) $client->getResponse()->getContent());
    }
}
