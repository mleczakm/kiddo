<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Infrastructure\Doctrine\Repository\UserRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class AuthRegistrationControllerTest extends AuthControllerTestCase
{
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
}
