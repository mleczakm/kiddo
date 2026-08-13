<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchReference;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchType;
use Doctrine\DBAL\Connection;

final readonly class SearchResultHydrator
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * @param list<SearchReference> $references
     * @return list<SearchResult>
     */
    public function hydrate(array $references): array
    {
        if ($references === []) {
            return [];
        }

        $values = [];
        $parameters = [];
        foreach ($references as $index => $reference) {
            $values[] = sprintf('(:type%d, :id%d)', $index, $index);
            $parameters['type' . $index] = $reference->type->value;
            $parameters['id' . $index] = $reference->id;
        }

        $sql = sprintf(<<<'SQL'
            WITH wanted(type, id) AS (VALUES %s), details AS (
                SELECT 'client' type, u.id::text id, u.name title,
                       concat_ws(' · ', u.email, u.phone, 'Rejestracja: ' || to_char(u.created_at, 'DD.MM.YYYY')) subtitle
                FROM "user" u
                UNION ALL
                SELECT 'child', c.id::text, c.name,
                       concat_ws(' · ', 'Dziecko', u.name, 'Dodano: ' || to_char(c.created_at, 'DD.MM.YYYY'))
                FROM child c JOIN "user" u ON u.id = c.owner_id
                UNION ALL
                SELECT 'booking', b.id::text, 'Rezerwacja · ' || u.name,
                       concat_ws(' · ', c.name, string_agg(DISTINCT lm.title, ', '), b.status,
                           to_char(b.created_at, 'DD.MM.YYYY HH24:MI'))
                FROM booking b JOIN "user" u ON u.id = b.user_id LEFT JOIN child c ON c.id = b.child_id
                LEFT JOIN booking_lesson bl ON bl.booking_id = b.id LEFT JOIN lesson l ON l.id = bl.lesson_id
                LEFT JOIN lesson_metadata lm ON lm.id = l.metadata_id GROUP BY b.id, u.name, c.name
                UNION ALL
                SELECT 'lesson', l.id::text, lm.title, concat_ws(' · ', lm.category, to_char(l.schedule, 'DD.MM.YYYY HH24:MI'))
                FROM lesson l JOIN lesson_metadata lm ON lm.id = l.metadata_id
                UNION ALL
                SELECT 'payment', p.id::text, 'Płatność · ' || u.name,
                       concat_ws(' · ', p.status, p.method,
                           replace(p.amount->>'amount', '.', ',') || ' ' ||
                               CASE p.amount->>'currency' WHEN 'PLN' THEN 'zł' ELSE p.amount->>'currency' END,
                           to_char(COALESCE(p.paid_at, p.created_at), 'DD.MM.YYYY HH24:MI'))
                FROM payment p JOIN "user" u ON u.id = p.user_id
                UNION ALL
                SELECT 'transfer', t.id::text, t.sender,
                       concat_ws(' · ', t.title, t.amount, to_char(t.transferred_at, 'DD.MM.YYYY HH24:MI'))
                FROM transfer t WHERE t.deleted_at IS NULL
            )
            SELECT d.type, d.id, d.title, d.subtitle FROM wanted w JOIN details d USING (type, id)
            SQL
            , implode(', ', $values));

        /** @var list<array{type: string, id: string, title: string, subtitle: string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $parameters);
        $byKey = [];
        foreach ($rows as $row) {
            $reference = new SearchReference(SearchType::from($row['type']), $row['id']);
            $byKey[$row['type'] . ':' . $row['id']] = new SearchResult($reference, $row['title'], $row['subtitle']);
        }

        $results = [];
        foreach ($references as $reference) {
            $result = $byKey[$reference->type->value . ':' . $reference->id] ?? null;
            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
