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
    public string $role;

    public ?string $primaryRoute;

    /** @var list<string> */
    public array $keywords;

    /** @var list<string> */
    public array $related;

    public \DateTimeImmutable $updatedAt;

    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public DocSection $section,
        DocArticleMetadata $metadata,
    ) {
        $this->role = $metadata->role;
        $this->primaryRoute = $metadata->primaryRoute;
        $this->keywords = $metadata->keywords;
        $this->related = $metadata->related;
        $this->updatedAt = $metadata->updatedAt;
    }

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
