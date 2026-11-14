<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Infrastructure\Doctrine\Repository\UserRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class AuthCodeControllerTest extends AuthControllerTestCase
{
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
}
