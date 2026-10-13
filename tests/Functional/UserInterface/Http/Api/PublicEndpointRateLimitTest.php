<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Consent\MarketingConsentManager;
use App\Application\Newsletter\NewsletterSubscriptionRequestParser;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\UserInterface\Http\Api\AuthController;
use App\UserInterface\Http\NewsletterController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('functional')]
final class PublicEndpointRateLimitTest extends KernelTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        /** @var CacheItemPoolInterface $rateCache */
        $rateCache = self::getContainer()->get('cache.rate_limiter');
        /** @var CacheItemPoolInterface $appCache */
        $appCache = self::getContainer()->get('cache.app');
        $rateCache->clear();
        $appCache->clear();
    }

    public function testNewsletterRecipientLimitSurvivesIpAndEmailCaseChanges(): void
    {
        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('isDoubleOptInConfigured')->willReturn(true);
        $brevo->expects(static::exactly(3))->method('sendDoubleOptInConfirmation');
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            static::assertSame(
                200,
                $this->subscribe('Victim@example.com', '192.0.2.' . $attempt, $brevo)->getStatusCode(),
            );
        }

        $response = $this->subscribe('victim@EXAMPLE.com', '198.51.100.1', $brevo);
        static::assertSame(429, $response->getStatusCode());
        static::assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
    }

    public function testNewsletterIpLimitBlocksRotatingRecipients(): void
    {
        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('isDoubleOptInConfigured')->willReturn(true);
        $brevo->expects(static::exactly(10))->method('sendDoubleOptInConfirmation');
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            static::assertSame(
                200,
                $this->subscribe('victim' . $attempt . '@example.com', '192.0.2.1', $brevo)->getStatusCode(),
            );
        }
        static::assertSame(429, $this->subscribe('another@example.com', '192.0.2.1', $brevo)->getStatusCode());
    }

    public function testLoginAndVerifyShareAccountLimitAcrossRotatingIps(): void
    {
        $container = self::getContainer();
        /** @var AuthController $controller */
        $controller = $container->get(AuthController::class);
        /** @var ValidatorInterface $validator */
        $validator = $container->get(ValidatorInterface::class);
        for ($attempt = 0; $attempt < 11; ++$attempt) {
            $request = $this->request([
                'email' => ($attempt % 2) === 0 ? 'Victim@example.com' : 'victim@EXAMPLE.com',
                'code' => '123456',
            ], '192.0.2.' . $attempt);
            $response = ($attempt % 2) === 0
                ? $controller->login($request, $validator)
                : $controller->verify($request, $validator);
            static::assertSame($attempt < 10 ? 400 : 429, $response->getStatusCode());
        }
    }

    public function testSendCodeChecksIpLimitBeforeAccountLookup(): void
    {
        /** @var AuthController $controller */
        $controller = self::getContainer()->get(AuthController::class);
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get(ValidatorInterface::class);
        for ($attempt = 0; $attempt < 21; ++$attempt) {
            $response = $controller->sendCode($this->request([
                'email' => 'unknown' . $attempt . '@example.com',
            ], '192.0.2.1'), $validator);
            static::assertSame($attempt < 20 ? 404 : 429, $response->getStatusCode());
        }
    }

    private function subscribe(string $email, string $ip, BrevoNewsletterService $brevo): JsonResponse
    {
        $container = self::getContainer();
        /** @var NewsletterController $controller */
        $controller = $container->get(NewsletterController::class);
        /** @var NewsletterSubscriptionRequestParser $parser */
        $parser = $container->get(NewsletterSubscriptionRequestParser::class);
        /** @var UserRepository $users */
        $users = $container->get(UserRepository::class);
        /** @var MarketingConsentManager $consent */
        $consent = $container->get(MarketingConsentManager::class);
        return $controller->subscribe(
            $this->request([
                'email' => $email,
                'consent' => true,
                'consentVersion' => MarketingConsentManager::VERSION,
            ], $ip),
            $parser,
            $users,
            $brevo,
            $consent,
        );
    }

    /** @param array<string, mixed> $data */
    private function request(array $data, string $ip): Request
    {
        return Request::create(
            '/',
            'POST',
            server: ['REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($data, JSON_THROW_ON_ERROR),
        );
    }
}
