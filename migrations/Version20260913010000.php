<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data fix for WARSZTATOWNIA-BV/AE: SendPaymentNotificationCommand carried a
 * live Payment entity through the async transport, so the task worker
 * received a detached entity graph. For these 6 payments, that crashed
 * before the customer's "Płatność potwierdzona" in-app notification could be
 * persisted (fixed in c02a5fa). The payments themselves were recorded
 * correctly — this only backfills the missing dashboard notification.
 * Deliberately does not resend the confirmation email.
 */
final class Version20260913010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill missing "Płatność potwierdzona" notifications lost to WARSZTATOWNIA-BV/AE';
    }

    public function up(Schema $schema): void
    {
        $rows = [
            [
                'id' => '01a097ce-59b3-a0ae-6a03-c620baefb74a',
                'user_id' => 264,
                'created_at' => '2026-08-13 08:46:46',
                'amount' => '60.00',
                'lesson' => 'Bałaganki',
            ],
            [
                'id' => '01a097ce-59cd-3262-759f-07de91284b3d',
                'user_id' => 74,
                'created_at' => '2026-08-18 20:42:47',
                'amount' => '45.00',
                'lesson' => 'TUS - DZIEŃ OTWARTY',
            ],
            [
                'id' => '01a097ce-59cf-e155-d697-ae0108b4c770',
                'user_id' => 270,
                'created_at' => '2026-08-19 10:00:38',
                'amount' => '60.00',
                'lesson' => 'Ćwiczymy mózg przez ruch',
            ],
            [
                'id' => '01a097ce-59d0-8091-c9f5-50736807823e',
                'user_id' => 158,
                'created_at' => '2026-08-24 08:03:28',
                'amount' => '40.00',
                'lesson' => 'KLUB MALUCHA - DZIEŃ OTWARTY',
            ],
            [
                'id' => '01a097ce-59d1-4906-29f7-a3023fed9985',
                'user_id' => 200,
                'created_at' => '2026-08-24 09:10:05',
                'amount' => '40.00',
                'lesson' => 'KLUB MALUCHA - DZIEŃ OTWARTY',
            ],
            [
                'id' => '01a097ce-59d2-3e0f-3fad-1c5322cee408',
                'user_id' => 207,
                'created_at' => '2026-08-25 11:40:41',
                'amount' => '60.00',
                'lesson' => 'Senso Bobasy',
            ],
        ];

        foreach ($rows as $row) {
            $title = 'Płatność potwierdzona';
            $body = sprintf(
                'Otrzymaliśmy płatność <strong>%s zł</strong> za <em>%s</em>.',
                $row['amount'],
                $row['lesson'],
            );

            // Guarded with WHERE EXISTS: these user ids only exist on
            // production, where this already ran. On any other database
            // (CI's fresh test schema, a new dev environment) this is a
            // harmless no-op instead of a foreign key violation.
            $this->addSql(<<<'SQL'
                INSERT INTO notification (id, user_id, created_at, severity, title, body, url)
                SELECT ?, ?, ?, 'success', ?, ?, '/panel'
                WHERE EXISTS (SELECT 1 FROM "user" WHERE id = ?)
                SQL, [
                $row['id'],
                $row['user_id'],
                $row['created_at'],
                $title,
                $body,
                $row['user_id'],
            ], ['string', 'integer', 'string', 'string', 'string', 'integer']);
        }
    }

    public function down(Schema $schema): void
    {
        $ids = [
            '01a097ce-59b3-a0ae-6a03-c620baefb74a',
            '01a097ce-59cd-3262-759f-07de91284b3d',
            '01a097ce-59cf-e155-d697-ae0108b4c770',
            '01a097ce-59d0-8091-c9f5-50736807823e',
            '01a097ce-59d1-4906-29f7-a3023fed9985',
            '01a097ce-59d2-3e0f-3fad-1c5322cee408',
        ];

        $this->addSql('DELETE FROM notification WHERE id IN (?, ?, ?, ?, ?, ?)', $ids, [
            'string',
            'string',
            'string',
            'string',
            'string',
            'string',
        ]);
    }
}
