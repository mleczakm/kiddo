<?php

declare(strict_types=1);

namespace App\Application\Help;

/**
 * One help article. The prose body lives in the Twig template returned by
 * {@see self::template()} (so it can call {@code path()} for always-correct panel
 * links); everything needed to list, gate and search the article lives here, parsed
 * from the template's frontmatter block by {@see DocRegistry}.
 */
final readonly class DocArticle
{
    /**
     * @param list<string> $keywords extra search terms not already in the title/summary
     * @param list<string> $related slugs of related articles, shown as cross-links
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public DocSection $section,
        public string $role,
        public ?string $primaryRoute,
        public array $keywords,
        public array $related,
        public \DateTimeImmutable $updatedAt,
    ) {}

    /** Twig template holding the article body. */
    public function template(): string
    {
        return 'admin/help/articles/' . $this->slug . '.html.twig';
    }

    /** Lower-cased text the global search matches against. */
    public function searchHaystack(): string
    {
        return mb_strtolower(implode(' ', [
            $this->title,
            $this->summary,
            implode(' ', $this->keywords),
            $this->section->label(),
        ]));
    }
}
