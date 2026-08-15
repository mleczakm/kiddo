<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Doctrine\DBAL\ParameterType;
use App\Application\Search\GlobalSearchQuery;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchType;
use Doctrine\DBAL\Connection;
use Ds\PriorityQueue;

final readonly class PostgresGlobalSearchQuery implements GlobalSearchQuery
{
    private const string SQL = <<<'SQL'
        SELECT type, id, score
        FROM (
            SELECT 'client' AS type, u.id::text AS id,
                   CASE WHEN to_char(u.created_at, 'DD.MM') = :query THEN 6
                        WHEN lower(u.name) = :query THEN 5 WHEN lower(u.name) LIKE :prefix THEN 4
                        WHEN lower(u.email) LIKE :prefix THEN 3 ELSE 1 END AS score
            FROM "user" u
            WHERE lower(concat_ws(' ', u.name, u.email, u.phone, u.admin_note, u.id::text,
                        to_char(u.created_at, 'DD.MM.YYYY'), to_char(u.created_at, 'DD.MM'))) LIKE :contains

            UNION ALL
            SELECT 'child', c.id::text,
                   CASE WHEN to_char(c.created_at, 'DD.MM') = :query THEN 6
                        WHEN lower(c.name) = :query THEN 5 WHEN lower(c.name) LIKE :prefix THEN 4
                        WHEN lower(u.name) LIKE :prefix THEN 3 ELSE 1 END
            FROM child c JOIN "user" u ON u.id = c.owner_id
            WHERE lower(concat_ws(' ', c.name, u.name, u.email, c.id::text,
                        to_char(c.created_at, 'DD.MM.YYYY'), to_char(c.created_at, 'DD.MM'))) LIKE :contains

            UNION ALL
            SELECT 'booking', b.id::text,
                   CASE WHEN to_char(b.created_at, 'DD.MM') = :query THEN 7
                        WHEN lower(b.id::text) = :query THEN 6 WHEN lower(b.id::text) LIKE :prefix THEN 5
                        WHEN lower(u.name) LIKE :prefix OR lower(COALESCE(c.name, '')) LIKE :prefix THEN 4
                        WHEN lower(COALESCE(string_agg(DISTINCT lm.title, ' '), '')) LIKE :prefix THEN 3 ELSE 1 END
            FROM booking b JOIN "user" u ON u.id = b.user_id
            LEFT JOIN child c ON c.id = b.child_id
            LEFT JOIN booking_lesson bl ON bl.booking_id = b.id
            LEFT JOIN lesson l ON l.id = bl.lesson_id
            LEFT JOIN lesson_metadata lm ON lm.id = l.metadata_id
            GROUP BY b.id, u.name, u.email, c.name, b.notes
            HAVING lower(concat_ws(' ', b.id::text, b.status, b.notes, u.name, u.email, c.name,
                       string_agg(DISTINCT lm.title, ' '), to_char(b.created_at, 'DD.MM.YYYY'),
                       to_char(b.created_at, 'DD.MM'))) LIKE :contains

            UNION ALL
            SELECT 'lesson', l.id::text,
                   CASE WHEN to_char(l.schedule, 'DD.MM') = :query THEN 6
                        WHEN lower(lm.title) = :query THEN 5 WHEN lower(lm.title) LIKE :prefix THEN 4
                        WHEN lower(lm.category) LIKE :prefix THEN 3 ELSE 1 END
            FROM lesson l JOIN lesson_metadata lm ON lm.id = l.metadata_id
            WHERE lower(concat_ws(' ', l.id::text, l.status, lm.title, lm.lead, lm.category, lm.description,
                        to_char(l.schedule, 'YYYY-MM-DD HH24:MI'), to_char(l.schedule, 'DD.MM.YYYY HH24:MI'),
                        to_char(l.schedule, 'DD.MM'))) LIKE :contains

            UNION ALL
            SELECT 'payment', p.id::text,
                   CASE WHEN to_char(COALESCE(p.paid_at, p.created_at), 'DD.MM') = :query THEN 7
                        WHEN lower(p.id::text) = :query THEN 6 WHEN lower(p.id::text) LIKE :prefix THEN 5
                        WHEN lower(COALESCE(p.payment_code_snapshot, '')) = :query THEN 5
                        WHEN lower(u.name) LIKE :prefix THEN 4 WHEN lower(u.email) LIKE :prefix THEN 3 ELSE 1 END
            FROM payment p JOIN "user" u ON u.id = p.user_id
            WHERE lower(concat_ws(' ', p.id::text, p.status, p.method, p.amount::text,
                        p.payment_code_snapshot, u.name, u.email,
                        to_char(p.created_at, 'DD.MM.YYYY'), to_char(p.created_at, 'DD.MM'),
                        to_char(p.paid_at, 'DD.MM.YYYY'), to_char(p.paid_at, 'DD.MM'))) LIKE :contains

            UNION ALL
            SELECT 'transfer', t.id::text,
                   CASE WHEN to_char(t.transferred_at, 'DD.MM') = :query THEN 6
                        WHEN lower(t.sender) = :query OR lower(t.title) = :query THEN 5
                        WHEN lower(t.sender) LIKE :prefix OR lower(t.title) LIKE :prefix THEN 4
                        WHEN lower(t.account_number) LIKE :prefix THEN 3 ELSE 1 END
            FROM transfer t
            WHERE t.deleted_at IS NULL
              AND lower(concat_ws(' ', t.id::text, t.sender, t.title, t.account_number, t.amount,
                          to_char(t.transferred_at, 'DD.MM.YYYY'), to_char(t.transferred_at, 'DD.MM'))) LIKE :contains
        ) matches
        ORDER BY score DESC, type, id
        LIMIT :limit
        SQL;

    public function __construct(
        private Connection $connection
    ) {}

    public function search(string $query, int $limit = 15): PriorityQueue
    {
        /** @var PriorityQueue<SearchReference> $queue */
        $queue = new PriorityQueue();
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 2) {
            return $queue;
        }

        /** @var list<array{type: string, id: string, score: string|float}> $rows */
        $rows = $this->connection->fetchAllAssociative(self::SQL, [
            'query' => $query,
            'prefix' => $query . '%',
            'contains' => '%' . $query . '%',
            'limit' => max(1, min($limit, 30)),
        ], [
            'limit' => ParameterType::INTEGER,
        ]);

        foreach ($rows as $row) {
            $queue->push(
                new SearchReference(SearchType::from($row['type']), $row['id']),
                (int) round((float) $row['score'] * 10_000),
            );
        }

        return $queue;
    }
}
