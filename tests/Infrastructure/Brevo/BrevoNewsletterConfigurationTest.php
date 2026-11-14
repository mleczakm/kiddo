<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Brevo;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\MockHttpClient;

#[Group('unit')]
final class BrevoNewsletterConfigurationTest extends BrevoNewsletterServiceTestCase
{
    public function testIsConfiguredReturnsTrueWhenApiKeyAndListIdSet(): void
    {
        $service = $this->createService(new MockHttpClient([]));

        static::assertTrue($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiKeyEmpty(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            '',
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        static::assertFalse($service->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenListIdZero(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            $this->apiKey,
            0,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );

        static::assertFalse($service->isConfigured());
    }

    public function testIsDoubleOptInConfiguredReturnsTrueWhenFullyConfigured(): void
    {
        $service = $this->createService(new MockHttpClient([]));

        static::assertTrue($service->isDoubleOptInConfigured());
    }

    public function testIsDoubleOptInConfiguredReturnsFalseWhenTemplateIdIsZero(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            $this->apiKey,
            self::LIST_ID,
            0,
            self::REDIRECTION_URL,
        );

        static::assertFalse($service->isDoubleOptInConfigured());
    }

    public function testIsDoubleOptInConfiguredReturnsFalseWhenRedirectionUrlIsEmpty(): void
    {
        $service = new BrevoNewsletterService(
            new MockHttpClient([]),
            $this->apiKey,
            self::LIST_ID,
            self::TEMPLATE_ID,
            '',
        );

        static::assertFalse($service->isDoubleOptInConfigured());
    }
}
