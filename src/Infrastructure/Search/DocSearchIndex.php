<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Help\DocArticle;
use App\Application\Help\DocRegistry;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * In-memory search over the help centre. Mirrors the integer priority scale of
 * {@see PostgresGlobalSearchQuery} (score 1-6, times 10 000) so help hits interleave
 * naturally with database hits in the global search dropdown.
 */
final readonly class DocSearchIndex
{
    public function __construct(
        private DocRegistry $registry,
        private AuthorizationCheckerInterface $authorizationChecker,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<ScoredReference> ordered by descending priority, capped at $limit
     */
    public function search(string $query, int $limit = 15): array
    {
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 2) {
            return [];
        }

        try {
            $articles = $this->registry->all();
        } catch (\RuntimeException $exception) {
            $this->logger->error('Unable to load help articles for search.', ['exception' => $exception]);

            return [];
        }

        $scored = [];
        foreach ($articles as $article) {
            if (!$this->authorizationChecker->isGranted($article->role)) {
                continue;
            }

            $score = $this->score($article, $query);
            if ($score > 0) {
                $scored[] = new ScoredReference(new SearchReference(SearchType::Doc, $article->slug), $score * 10_000);
            }
        }

        usort($scored, static fn(ScoredReference $a, ScoredReference $b): int => $b->priority <=> $a->priority);

        return array_slice($scored, 0, max(1, $limit));
    }

    private function score(DocArticle $article, string $query): int
    {
        $title = mb_strtolower($article->title);
        $keywords = array_map(mb_strtolower(...), $article->keywords);

        return match (true) {
            $title === $query => 6,
            in_array($query, $keywords, true), str_starts_with($title, $query) => 5,
            str_contains($title, $query) => 4,
            $this->anyContains($keywords, $query) => 3,
            str_contains(mb_strtolower($article->summary), $query),
            str_contains(mb_strtolower($article->section->label()), $query),
                => 2,
            str_contains($article->searchHaystack(), $query) => 1,
            default => 0,
        };
    }

    /**
     * @param list<string> $values
     */
    private function anyContains(array $values, string $needle): bool
    {
        foreach ($values as $value) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
