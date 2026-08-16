<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchReference;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchType;
use App\Application\Service\TransferMoneyParser;
use App\Infrastructure\Twig\MoneyExtension;
use Brick\Money\Money;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SearchResultHydrator
{
    public function __construct(
        private Connection $connection,
        private TranslatorInterface $translator,
        private MoneyExtension $moneyExtension,
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
            WITH wanted(entity_type, id) AS (VALUES %s), details AS (
                SELECT 'client' AS entity_type, u.id::text AS id, u.name AS title,
                       u.email AS part1, u.phone AS part2, NULL::text AS status,
                       NULL::text AS amount_value, NULL::text AS amount_currency, NULL::text AS amount_text,
                       'Rejestracja: ' || to_char(u.created_at, 'DD.MM.YYYY') AS timestamp_str
                FROM "user" u
                UNION ALL
                SELECT 'child', c.id::text, c.name,
                       'Dziecko', u.name, NULL, NULL, NULL, NULL,
                       'Dodano: ' || to_char(c.created_at, 'DD.MM.YYYY')
                FROM child c JOIN "user" u ON u.id = c.owner_id
                UNION ALL
                SELECT 'booking', b.id::text, 'Rezerwacja · ' || u.name,
                       c.name, string_agg(DISTINCT lm.title, ', '), b.status, NULL, NULL, NULL,
                       to_char(b.created_at, 'DD.MM.YYYY HH24:MI')
                FROM booking b JOIN "user" u ON u.id = b.user_id LEFT JOIN child c ON c.id = b.child_id
                LEFT JOIN booking_lesson bl ON bl.booking_id = b.id LEFT JOIN lesson l ON l.id = bl.lesson_id
                LEFT JOIN lesson_metadata lm ON lm.id = l.metadata_id GROUP BY b.id, u.name, c.name
                UNION ALL
                SELECT 'lesson', l.id::text, lm.title, lm.category, NULL, NULL, NULL, NULL, NULL,
                       to_char(l.schedule, 'DD.MM.YYYY HH24:MI')
                FROM lesson l JOIN lesson_metadata lm ON lm.id = l.metadata_id
                UNION ALL
                SELECT 'payment', p.id::text, 'Płatność · ' || u.name,
                       p.method, NULL, p.status, p.amount->>'amount', p.amount->>'currency', NULL,
                       to_char(COALESCE(p.paid_at, p.created_at), 'DD.MM.YYYY HH24:MI')
                FROM payment p JOIN "user" u ON u.id = p.user_id
                UNION ALL
                SELECT 'transfer', t.id::text, t.sender,
                       t.title, NULL, NULL, NULL, NULL, t.amount,
                       to_char(t.transferred_at, 'DD.MM.YYYY HH24:MI')
                FROM transfer t WHERE t.deleted_at IS NULL
            )
            SELECT d.entity_type AS "type", d.id, d.title, d.part1, d.part2, d.status,
                   d.amount_value, d.amount_currency, d.amount_text, d.timestamp_str
            FROM wanted w
            JOIN details d USING (entity_type, id)
            SQL
            , implode(', ', $values));

        /**
         * @var list<array{
         *     type: string, id: string, title: string, part1: ?string, part2: ?string, status: ?string,
         *     amount_value: ?string, amount_currency: ?string, amount_text: ?string, timestamp_str: ?string,
         * }> $rows
         */
        $rows = $this->connection->fetchAllAssociative($sql, $parameters);
        $byKey = [];
        foreach ($rows as $row) {
            $type = SearchType::from($row['type']);
            $reference = new SearchReference($type, $row['id']);
            $subtitle = $this->buildSubtitle($type, $row);
            $byKey[$row['type'] . ':' . $row['id']] = new SearchResult($reference, $row['title'], $subtitle);
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

    /**
     * @param array{
     *     part1: ?string, part2: ?string, status: ?string,
     *     amount_value: ?string, amount_currency: ?string, amount_text: ?string, timestamp_str: ?string,
     * } $row
     */
    private function buildSubtitle(SearchType $type, array $row): string
    {
        $parts = match ($type) {
            SearchType::Client, SearchType::Child, SearchType::Lesson => [
                $row['part1'],
                $row['part2'],
                $row['timestamp_str'],
            ],
            SearchType::Booking => [
                $row['part1'],
                $row['part2'],
                $row['status'] !== null ? $this->translator->trans($row['status']) : null,
                $row['timestamp_str'],
            ],
            SearchType::Payment => [
                $row['status'] !== null ? $this->translator->trans($row['status']) : null,
                $row['part1'],
                $row['amount_value'] !== null && $row['amount_currency'] !== null
                    ? $this->moneyExtension->formatMoney(Money::of($row['amount_value'], $row['amount_currency']))
                    : null,
                $row['timestamp_str'],
            ],
            SearchType::Transfer => [
                $row['part1'],
                $row['amount_text'] !== null
                    ? $this->moneyExtension->formatMoney(
                        TransferMoneyParser::transferMoneyStringToMoneyObject($row['amount_text'])
                    )
                    : null,
                $row['timestamp_str'],
            ],
        };

        return implode(' · ', array_filter($parts, static fn(?string $part): bool => $part !== null && $part !== ''));
    }
}
