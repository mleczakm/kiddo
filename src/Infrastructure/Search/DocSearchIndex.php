<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Help\DocArticleMatcher;
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
        private DocArticleMatcher $matcher,
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

            $score = $this->matcher->score($article, $query);
            if ($score > 0) {
                $scored[] = new ScoredReference(new SearchReference(SearchType::Doc, $article->slug), $score * 10_000);
            }
        }

        usort($scored, static fn(ScoredReference $a, ScoredReference $b): int => $b->priority <=> $a->priority);

        return array_slice($scored, 0, max(1, $limit));
    }
}
