<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

final class BankMailWebhookTestSupport
{
    private const string ADDRESS = '0123456789abcdef0123456789abcdef@warsztatowniasensoryczna.pl';
    private const string FROM = 'powiadomienia@alior.pl';

    private static ?string $testSecret = null;

    /** @param array<string, string> $overrides */
    public static function payload(array $overrides = []): string
    {
        return json_encode(array_replace([
            'id' => 'test-bank-mail-1@alior.pl',
            'received_at' => '2026-10-05T08:15:30.000Z',
            'recipient' => self::ADDRESS,
            'mail_from' => self::FROM,
            'subject' => 'Uznanie rachunku 91...1234 kwotą 50,00 PLN',
            'html' => '<html><br/>kwotą 50,00 PLN<br/>Nadawca: Test Sender<br/>Tytuł zlecenia: KIDDO1<br/></html>',
            'text' => 'Uznanie rachunku',
        ], $overrides), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    public static function signedHeaders(string $body): array
    {
        $timestamp = (string) time();

        return [
            'HTTP_X_KIDDO_BANK_TIMESTAMP' => $timestamp,
            'HTTP_X_KIDDO_BANK_SIGNATURE' => self::signature($timestamp, $body),
        ];
    }

    public static function signature(string $timestamp, string $body): string
    {
        return 'v1=' . hash_hmac('sha256', $timestamp . "\n" . $body, self::secret());
    }

    public static function setBankMailEnvironment(): void
    {
        self::setEnv('BANK_MAIL_ADDRESS', self::ADDRESS);
        self::setEnv('BANK_MAIL_WEBHOOK_SECRET', self::secret());
        self::setEnv('BANK_MAIL_FROM', self::FROM);
    }

    public static function setEnv(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }

    private static function secret(): string
    {
        self::$testSecret ??= bin2hex(random_bytes(32));

        return self::$testSecret;
    }
}
