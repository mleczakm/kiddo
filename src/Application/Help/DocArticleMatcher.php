<?php

declare(strict_types=1);

namespace App\Application\Help;

/**
 * Relevance scoring of a help article against a free-text query fragment. Shared
 * by the global-search index ({@see \App\Infrastructure\Search\DocSearchIndex})
 * and the chat assistant ({@see \App\Application\Chat\HelpChatTools}) so both
 * rank knowledge-base articles the same way. A score of 0 means "no match".
 */
final readonly class DocArticleMatcher
{
    /**
     * @return int 0 (no match) to 6 (exact title hit)
     */
    public function score(DocArticle $article, string $query): int
    {
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 2) {
            return 0;
        }

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
