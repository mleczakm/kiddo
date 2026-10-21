<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchReference;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchType;
use App\Application\Service\RecommendationProvider;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class RecommendationSearchIndex
{
    public function __construct(
        private RecommendationProvider $provider,
        private AuthorizationCheckerInterface $authorization,
    ) {}

    /** @return list<ScoredReference> */
    public function search(string $query, int $limit): array
    {
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 2 || !$this->authorization->isGranted('ROLE_SETTINGS')) {
            return [];
        }
        $results = [];
        if (str_contains('opinie rodziców rekomendacje recenzje szkice draft zatwierdzanie', $query)) {
            $results[] = new ScoredReference(new SearchReference(SearchType::Recommendation, 'module'), 60_000);
        }
        foreach (array_slice($this->provider->search($query), 0, max(1, $limit)) as $review) {
            $results[] = new ScoredReference(new SearchReference(SearchType::Recommendation, $review['id']), 40_000);
        }
        return $results;
    }

    /**
     * @param list<SearchReference> $references
     * @return array<string, SearchResult>
     */
    public function hydrate(array $references): array
    {
        if (!$this->authorization->isGranted('ROLE_SETTINGS')) {
            return [];
        }
        $rows = array_column($this->provider->getAll(), null, 'id');
        $results = [];
        foreach ($references as $reference) {
            if ($reference->type !== SearchType::Recommendation) {
                continue;
            }
            if ($reference->id === 'module') {
                $results['recommendation:module'] = new SearchResult(
                    $reference,
                    'Opinie rodziców',
                    'Dodawanie, edycja i zatwierdzanie opinii użytkowników',
                );
                continue;
            }
            $row = $rows[$reference->id] ?? null;
            if ($row !== null) {
                $results['recommendation:' . $reference->id] = new SearchResult(
                    $reference,
                    'Opinia · ' . $row['author'],
                    ($row['status'] === 'draft' ? 'Szkic do zatwierdzenia' : 'Zatwierdzona') . ' · '
                        . mb_substr($row['text'], 0, 120),
                );
            }
        }
        return $results;
    }
}
