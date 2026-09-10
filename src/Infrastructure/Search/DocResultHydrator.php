<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Help\DocArticle;
use App\Application\Help\DocRegistry;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchType;

/**
 * Turns {@see SearchType::Doc} references into {@see SearchResult}s from the help
 * registry. Kept separate from {@see SearchResultHydrator} so the database
 * hydration path stays untouched.
 */
final readonly class DocResultHydrator
{
    public function __construct(
        private DocRegistry $registry,
    ) {}

    /**
     * @param list<SearchReference> $references
     * @return array<string, SearchResult> keyed as "doc:<slug>"
     */
    public function hydrate(array $references): array
    {
        $byKey = [];
        foreach ($references as $reference) {
            if ($reference->type !== SearchType::Doc) {
                continue;
            }

            $article = $this->lookup($reference->id);
            if ($article === null) {
                continue;
            }

            $byKey['doc:' . $reference->id] = new SearchResult(
                $reference,
                $article->title,
                $article->section->label() . ' · ' . $article->summary,
            );
        }

        return $byKey;
    }

    private function lookup(string $slug): ?DocArticle
    {
        try {
            return $this->registry->get($slug);
        } catch (\RuntimeException) {
            return null;
        }
    }
}
