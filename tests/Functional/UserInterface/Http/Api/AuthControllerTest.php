<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Repository\UserRepository;
use App\UserInterface\Http\Api\AuthController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Mailer\Test\InteractsWithMailer;

/**
 * Calls AuthController directly (not through the HTTP test client): the client's kernel.terminate
 * cycle resets the in-memory rate-limiter/verification-code cache pools between requests in the
 * test env, which would wipe state a multi-step register -> verify flow depends on.
 */
#[Group('functional')]
final class AuthControllerTest extends WebTestCase
{
    use InteractsWithMailer;

    private AuthController $controller;

    private ValidatorInterface $validator;

    private CacheItemPoolInterface $appCache;

    private CacheItemPoolInterface $rateLimiterCache;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        $container = static::getContainer();
        $this->controller = $container->get(AuthController::class);
        $this->validator = $container->get(ValidatorInterface::class);
        $this->appCache = $container->get('cache.app');
        $this->rateLimiterCache = $container->get('cache.rate_limiter');
        $this->appCache->clear();
        $this->rateLimiterCache->clear();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->appCache->clear();
        $this->rateLimiterCache->clear();

        parent::tearDown();
    }

    public function testRegisterThenVerifyIssuesChatToken(): void
    {
        $email = 'auth-flow@example.com';

        $registerResponse = $this->register([
            'email' => $email,
            'name' => 'Auth Flow',
        ]);
        static::assertSame(201, $registerResponse->getStatusCode());
        $registerPayload = $this->decode($registerResponse);
        static::assertArrayNotHasKey('verification_code', $registerPayload);
        static::assertTrue($registerPayload['requires_verification']);

        $this->assertEmailCount(1);
        $sentEmail = $this->mailer()->sentEmails()->first();
        $sentEmail->assertSubject('Twój kod weryfikacyjny');
        $code = $this->extractCode((string) $sentEmail->getHtmlBody() . (string) $sentEmail->getTextBody());

        $verifyResponse = $this->verify([
            'email' => $email,
            'code' => $code,
        ]);
        static::assertSame(200, $verifyResponse->getStatusCode());
        /** @var array{chat_token: string, user: array{email: string}} $verifyPayload */
        $verifyPayload = $this->decode($verifyResponse);
        static::assertArrayHasKey('chat_token', $verifyPayload);
        static::assertSame($email, $verifyPayload['user']['email']);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);
        static::assertNotNull($user);
        static::assertNotNull($user->getConfirmedAt());

        // Codes are single-use.
        $replayResponse = $this->verify([
            'email' => $email,
            'code' => $code,
        ]);
        static::assertSame(400, $replayResponse->getStatusCode());
    }

    public function testVerifyWithWrongCodeFails(): void
    {
        $email = 'auth-wrong-code@example.com';

        $registerResponse = $this->register([
            'email' => $email,
            'name' => 'Wrong Code',
        ]);
        static::assertSame(201, $registerResponse->getStatusCode());

        $verifyResponse = $this->verify([
            'email' => $email,
            'code' => '000000',
        ]);
        static::assertSame(400, $verifyResponse->getStatusCode());
        static::assertArrayNotHasKey('chat_token', $this->decode($verifyResponse));
    }

    public function testLoginConsumesCodeAndRecordsLastLogin(): void
    {
        $email = 'auth-login@example.com';
        static::assertSame(
            201,
            $this->register([
                'email' => $email,
                'name' => 'Login Flow',
            ])->getStatusCode(),
        );

        $sentEmail = $this->mailer()->sentEmails()->first();
        $code = $this->extractCode((string) $sentEmail->getHtmlBody() . (string) $sentEmail->getTextBody());
        $response = $this->login([
            'email' => $email,
            'code' => $code,
        ]);

        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('chat_token', $this->decode($response));

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);
        static::assertNotNull($user);
        static::assertNotNull($user->getLastLoginAt());
        static::assertSame(
            400,
            $this->login([
                'email' => $email,
                'code' => $code,
            ])->getStatusCode(),
        );
    }

    public function testRegisterRejectsMalformedJson(): void
    {
        $request = Request::create(
            uri: '/api/auth/_test',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{invalid',
        );

        $response = $this->controller->register($request, $this->validator);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('Invalid JSON', $this->decode($response)['error']);
    }

    public function testSendCodeIsRateLimitedPerEmail(): void
    {
        $email = 'auth-rate-limit@example.com';

        // register() consumes the first of 3 email-limiter slots (shared budget with send-code).
        static::assertSame(
            201,
            $this->register([
                'email' => $email,
                'name' => 'Rate Limit',
            ])->getStatusCode(),
        );

        // 2nd and 3rd slots.
        static::assertSame(200, $this->sendCode($email)->getStatusCode());
        static::assertSame(200, $this->sendCode($email)->getStatusCode());

        // 4th request exceeds the 3/hour budget.
        static::assertSame(429, $this->sendCode($email)->getStatusCode());
    }

    public function testLoginIsRateLimitedPerIp(): void
    {
        $email = 'auth-ip-limit@example.com';

        for ($i = 0; $i < 20; ++$i) {
            $response = $this->login([
                'email' => $email,
                'code' => '123456',
            ]);
            static::assertSame(
                400,
                $response->getStatusCode(),
                "attempt {$i} should be a plain invalid-code rejection",
            );
        }

        static::assertSame(
            429,
            $this->login([
                'email' => $email,
                'code' => '123456',
            ])->getStatusCode(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function register(array $payload): JsonResponse
    {
        return $this->controller->register($this->jsonRequest($payload), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verify(array $payload): JsonResponse
    {
        return $this->controller->verify($this->jsonRequest($payload), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function login(array $payload): JsonResponse
    {
        return $this->controller->login($this->jsonRequest($payload), $this->validator);
    }

    private function sendCode(string $email): JsonResponse
    {
        return $this->controller->sendCode($this->jsonRequest([
            'email' => $email,
        ]), $this->validator);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            uri: '/api/auth/_test',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        /** @var array<string, mixed> $data */
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function extractCode(string $body): string
    {
        self::assertMatchesRegularExpression('/\d{6}/', $body);
        $matches = [];
        preg_match('/\d{6}/', $body, $matches);

        return $matches[0] ?? '';
    }
}
