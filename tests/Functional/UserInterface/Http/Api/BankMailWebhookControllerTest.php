<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Api;

use App\Application\Repository\TransferRepositoryInterface;
use App\Entity\Transfer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class BankMailWebhookControllerTest extends WebTestCase
{
    public function testValidSignatureImportsOnceAndAcceptsRedelivery(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();
        $body = BankMailWebhookTestSupport::payload();

        $client->request(
            'POST',
            '/api/bank-mail',
            server: BankMailWebhookTestSupport::signedHeaders($body),
            content: $body,
        );
        static::assertResponseStatusCodeSame(204);
        static::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('cache-control'));

        $client->request(
            'POST',
            '/api/bank-mail',
            server: BankMailWebhookTestSupport::signedHeaders($body),
            content: $body,
        );
        static::assertResponseStatusCodeSame(204);

        /** @var TransferRepositoryInterface $transfers */
        $transfers = self::getContainer()->get(TransferRepositoryInterface::class);
        $transfer = $transfers->findOneBy(['messageId' => 'test-bank-mail-1@alior.pl']);
        static::assertNotNull($transfer, 'The accepted notification creates a transfer');

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        static::assertSame(
            1,
            (int) $connection->fetchOne('SELECT COUNT(*) FROM bank_mail_receipt WHERE id = :id', [
                'id' => 'test-bank-mail-1@alior.pl',
            ]),
        );
        static::assertSame('imported', $connection->fetchOne('SELECT status FROM bank_mail_receipt WHERE id = :id', [
            'id' => 'test-bank-mail-1@alior.pl',
        ]));
    }

    public function testInvalidSignatureAndExpiredTimestampAreRejectedBeforeImport(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();
        $body = BankMailWebhookTestSupport::payload();

        $client->request(
            'POST',
            '/api/bank-mail',
            server: [
                'HTTP_X_KIDDO_BANK_TIMESTAMP' => (string) time(),
                'HTTP_X_KIDDO_BANK_SIGNATURE' => 'v1=' . str_repeat('0', 64),
            ],
            content: $body,
        );
        static::assertResponseStatusCodeSame(401);

        $staleTimestamp = (string) (time() - 301);
        $client->request(
            'POST',
            '/api/bank-mail',
            server: [
                'HTTP_X_KIDDO_BANK_TIMESTAMP' => $staleTimestamp,
                'HTTP_X_KIDDO_BANK_SIGNATURE' => BankMailWebhookTestSupport::signature($staleTimestamp, $body),
            ],
            content: $body,
        );
        static::assertResponseStatusCodeSame(401);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM bank_mail_receipt'));
    }

    public function testRecipientSenderAndPayloadAreValidatedAfterSignature(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();

        foreach ([
            BankMailWebhookTestSupport::payload(['recipient' => 'wrong@warsztatowniasensoryczna.pl']),
            BankMailWebhookTestSupport::payload(['mail_from' => 'attacker@example.test']),
            BankMailWebhookTestSupport::payload(['received_at' => 'tomorrow']),
            '{"incomplete":true}',
        ] as $index => $body) {
            $client->request(
                'POST',
                '/api/bank-mail',
                server: BankMailWebhookTestSupport::signedHeaders($body),
                content: $body,
            );
            static::assertResponseStatusCodeSame(400, 'Payload case ' . $index);
        }

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM bank_mail_receipt'));
    }

    public function testOversizedBodyAndMissingConfigurationAreRejected(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();
        $largeBody = str_repeat('x', (128 * 1024) + 1);
        $client->request(
            'POST',
            '/api/bank-mail',
            server: BankMailWebhookTestSupport::signedHeaders($largeBody),
            content: $largeBody,
        );
        static::assertResponseStatusCodeSame(413);

        static::ensureKernelShutdown();
        BankMailWebhookTestSupport::setEnv('BANK_MAIL_ADDRESS', '');
        $client = static::createClient();
        $body = BankMailWebhookTestSupport::payload();
        $client->request('POST', '/api/bank-mail', content: $body);
        static::assertResponseStatusCodeSame(503);
    }

    public function testUnrecognizedNotificationIsRecordedWithoutCreatingATransfer(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();
        $body = BankMailWebhookTestSupport::payload([
            'id' => 'unknown-bank-mail@alior.pl',
            'subject' => 'Monthly statement',
            'html' => '<p>Not a payment confirmation</p>',
        ]);

        $client->request(
            'POST',
            '/api/bank-mail',
            server: BankMailWebhookTestSupport::signedHeaders($body),
            content: $body,
        );
        static::assertResponseStatusCodeSame(204);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        static::assertSame('unrecognized', $connection->fetchOne('SELECT status FROM bank_mail_receipt WHERE id = :id', [
            'id' => 'unknown-bank-mail@alior.pl',
        ]));
        static::assertSame(
            0,
            (int) $connection->fetchOne('SELECT COUNT(*) FROM transfer WHERE message_id = :id', [
                'id' => 'unknown-bank-mail@alior.pl',
            ]),
        );
    }

    public function testLegacyTransferWithoutReceiptIsStillDeduplicatedDuringMigration(): void
    {
        BankMailWebhookTestSupport::setBankMailEnvironment();
        $client = static::createClient();
        $messageId = 'legacy-bank-mail@alior.pl';

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $legacyTransfer = new Transfer('91...1234', 'Legacy Sender', 'OLD-CODE', '20,00', new \DateTimeImmutable());
        $legacyTransfer->setMessageId($messageId);
        $entityManager->persist($legacyTransfer);
        $entityManager->flush();

        $body = BankMailWebhookTestSupport::payload(['id' => $messageId]);
        $client->request(
            'POST',
            '/api/bank-mail',
            server: BankMailWebhookTestSupport::signedHeaders($body),
            content: $body,
        );
        static::assertResponseStatusCodeSame(204);

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        static::assertSame(
            1,
            (int) $connection->fetchOne('SELECT COUNT(*) FROM transfer WHERE message_id = :id', ['id' => $messageId]),
        );
        static::assertSame('duplicate', $connection->fetchOne('SELECT status FROM bank_mail_receipt WHERE id = :id', [
            'id' => $messageId,
        ]));
    }
}
