<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
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
        $code = $this->extractCode((string) $sentEmail->getTextBody() . (string) $sentEmail->getHtmlBody());

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
        $code = $this->extractCode((string) $sentEmail->getTextBody() . (string) $sentEmail->getHtmlBody());
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
                'email' => $i . '-' . $email,
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
                'email' => $i . '-' . $email,
                'code' => '123456',
            ])->getStatusCode(),
        );
    }

    public function testRegisterRejectsInvalidPayloads(): void
    {
        $invalid = [
            'malformed email' => [
                'email' => 'not-an-email',
                'name' => 'Valid Name',
            ],
            'blank email' => [
                'email' => '',
                'name' => 'Valid Name',
            ],
            'too short name' => [
                'email' => 'auth-short-name@example.com',
                'name' => 'A',
            ],
            'missing name' => [
                'email' => 'auth-missing-name@example.com',
            ],
            'unexpected field' => [
                'email' => 'auth-extra-field@example.com',
                'name' => 'Valid Name',
                'role' => 'ROLE_ADMIN',
            ],
        ];

        foreach ($invalid as $label => $payload) {
            $response = $this->register($payload);

            static::assertSame(400, $response->getStatusCode(), $label);
            static::assertSame('Validation failed', $this->decode($response)['error'], $label);
        }

        $this->assertEmailCount(0);
    }

    public function testRegisterRejectsAnUnparsablePhoneNumberWithoutCreatingTheUser(): void
    {
        $email = 'auth-bad-phone@example.com';

        $response = $this->register([
            'email' => $email,
            'name' => 'Bad Phone',
            'phone' => 'definitely not a number',
        ]);

        static::assertSame(400, $response->getStatusCode());
        static::assertSame('Invalid phone number', $this->decode($response)['error']);
        static::assertNull($this->findUser($email));
        $this->assertEmailCount(0);
    }

    public function testRegisterStoresAPolishPhoneNumberWhenProvided(): void
    {
        $email = 'auth-phone@example.com';

        $response = $this->register([
            'email' => $email,
            'name' => 'With Phone',
            'phone' => '501 234 567',
        ]);

        static::assertSame(201, $response->getStatusCode());
        $user = $this->findUser($email);
        static::assertNotNull($user);
        static::assertSame(48, $user->getPhone()?->getCountryCode());
        static::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testVerifyAndLoginRejectMalformedRequests(): void
    {
        $malformedBody = Request::create(
            uri: '/api/auth/_test',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: '{invalid',
        );

        foreach (['verify', 'login'] as $action) {
            $response = $this->controller->{$action}($malformedBody, $this->validator);
            static::assertSame(400, $response->getStatusCode(), $action);
            static::assertSame('Invalid JSON', $this->decode($response)['error'], $action);
        }

        $invalid = [
            'malformed email' => [
                'email' => 'nope',
                'code' => '123456',
            ],
            'five digit code' => [
                'email' => 'auth-short-code@example.com',
                'code' => '12345',
            ],
            'seven digit code' => [
                'email' => 'auth-long-code@example.com',
                'code' => '1234567',
            ],
            'missing code' => [
                'email' => 'auth-no-code@example.com',
            ],
            'numeric code instead of a string' => [
                'email' => 'auth-int-code@example.com',
                'code' => 123_456,
            ],
        ];
        foreach ($invalid as $label => $payload) {
            static::assertSame(400, $this->verify($payload)->getStatusCode(), 'verify: ' . $label);
            static::assertSame(400, $this->login($payload)->getStatusCode(), 'login: ' . $label);
        }
    }

    public function testVerifyAndLoginRejectACodeForAnEmailThatNeverRequestedOne(): void
    {
        $payload = [
            'email' => 'auth-never-asked@example.com',
            'code' => '123456',
        ];

        $verify = $this->verify($payload);
        $login = $this->login($payload);

        static::assertSame(400, $verify->getStatusCode());
        static::assertSame('Invalid or expired code', $this->decode($verify)['error']);
        static::assertSame(400, $login->getStatusCode());
        static::assertSame('Invalid or expired code', $this->decode($login)['error']);
        static::assertArrayNotHasKey('chat_token', $this->decode($login));
    }

    public function testLoginWithAWrongCodeDoesNotRecordALoginOrBurnTheRealCode(): void
    {
        $email = 'auth-login-wrong@example.com';
        static::assertSame(
            201,
            $this->register([
                'email' => $email,
                'name' => 'Wrong Login',
            ])->getStatusCode(),
        );
        $sentEmail = $this->mailer()->sentEmails()->first();
        $code = $this->extractCode((string) $sentEmail->getTextBody() . (string) $sentEmail->getHtmlBody());
        $wrong = $code === '000000' ? '111111' : '000000';

        static::assertSame(
            400,
            $this->login([
                'email' => $email,
                'code' => $wrong,
            ])->getStatusCode(),
        );
        static::assertNull($this->findUser($email)?->getLastLoginAt());

        $response = $this->login([
            'email' => $email,
            'code' => $code,
        ]);
        static::assertSame(200, $response->getStatusCode());
        /** @var array{user: array{email: string, name: string}} $payload */
        $payload = $this->decode($response);
        static::assertSame($email, $payload['user']['email']);
        static::assertSame('Wrong Login', $payload['user']['name']);
    }

    public function testRegisterIsRateLimitedPerIp(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            static::assertSame(
                400,
                $this->register([
                    'email' => 'invalid',
                    'name' => 'Spammer',
                ])->getStatusCode(),
                "attempt {$i} should be a plain validation rejection",
            );
        }

        static::assertSame(
            429,
            $this->register([
                'email' => 'invalid',
                'name' => 'Spammer',
            ])->getStatusCode(),
        );
    }

    private function findUser(string $email): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        return $userRepository->findOneBy([
            'email' => $email,
        ]);
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

    /**
     * Matches the first run of 6 digits, so callers must pass the plain-text body
     * first: the HTML body's inline CSS can contain an all-numeric 6-digit hex
     * color (e.g. #583818) ahead of the real code.
     */
    private function extractCode(string $body): string
    {
        self::assertMatchesRegularExpression('/\d{6}/', $body);
        $matches = [];
        preg_match('/\d{6}/', $body, $matches);

        return $matches[0] ?? '';
    }
}
