<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Brevo;

use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

abstract class BrevoNewsletterServiceTestCase extends TestCase
{
    protected string $apiKey;

    #[\Override]
    protected function setUp(): void
    {
        $this->apiKey = bin2hex(random_bytes(16));
    }

    protected const int LIST_ID = 42;

    protected const int TEMPLATE_ID = 7;

    protected const string REDIRECTION_URL = 'http://example.test/newsletter-confirmed';

    protected function createService(MockHttpClient $httpClient): BrevoNewsletterService
    {
        return new BrevoNewsletterService(
            $httpClient,
            $this->apiKey,
            self::LIST_ID,
            self::TEMPLATE_ID,
            self::REDIRECTION_URL,
        );
    }
}
