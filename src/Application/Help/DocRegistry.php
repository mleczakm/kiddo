<?php

declare(strict_types=1);

namespace App\Application\Help;

/**
 * Loads the help articles from Twig templates on disk (no database). Each template
 * begins with a {@code {#--- ... ---#}} YAML frontmatter block describing the article;
 * the rest of the file is its rendered body.
 */
final class DocRegistry
{
    /** @var array<string, DocArticle>|null slug => article, ordered for display */
    private ?array $articles = null;

    public function __construct(
        private readonly string $articlesDir,
    ) {}

    /**
     * @return list<DocArticle> ordered by section then title
     *
     * @throws \RuntimeException when an article template has a malformed frontmatter block
     */
    public function all(): array
    {
        return array_values($this->load());
    }

    /**
     * @throws \RuntimeException when an article template has a malformed frontmatter block
     */
    public function get(string $slug): ?DocArticle
    {
        return $this->load()[$slug] ?? null;
    }

    /**
     * @return array<string, DocArticle>
     *
     * @throws \RuntimeException
     */
    private function load(): array
    {
        if ($this->articles !== null) {
            return $this->articles;
        }

        $files = glob(rtrim($this->articlesDir, '/') . '/*.html.twig');
        if ($files === false) {
            throw new \RuntimeException(sprintf('Unable to read help articles from "%s".', $this->articlesDir));
        }

        $articles = [];
        foreach ($files as $file) {
            $article = $this->parse($file);
            if (array_key_exists($article->slug, $articles)) {
                throw new \RuntimeException(sprintf('Duplicate help article slug "%s".', $article->slug));
            }

            $articles[$article->slug] = $article;
        }

        uasort(
            $articles,
            static fn(DocArticle $a, DocArticle $b): int => (
                [$a->section->order(), $a->title] <=> [$b->section->order(), $b->title]
            ),
        );

        return $this->articles = $articles;
    }

    /**
     * @throws \RuntimeException
     */
    private function parse(string $file): DocArticle
    {
        $meta = DocFrontmatter::fromFile($file);
        $slug = $meta->string('slug');

        if ($slug !== basename($file, '.html.twig') || preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            throw new \RuntimeException(sprintf(
                'Help article "%s" must have a kebab-case slug matching its file name.',
                $file,
            ));
        }

        $section = DocSection::tryFrom($meta->string('section')) ?? throw new \RuntimeException(sprintf(
            'Help article "%s" has an unknown section.',
            $file,
        ));

        $role = $meta->string('role');
        if (!str_starts_with($role, 'ROLE_')) {
            throw new \RuntimeException(sprintf('Help article "%s" role must be a ROLE_* string.', $file));
        }

        $mtime = filemtime($file);

        return new DocArticle(
            slug: $slug,
            title: $meta->string('title'),
            summary: $meta->string('summary'),
            section: $section,
            role: $role,
            primaryRoute: $meta->optionalString('primary_route'),
            keywords: $meta->stringList('keywords'),
            related: $meta->stringList('related'),
            updatedAt: new \DateTimeImmutable('@' . ($mtime === false ? '0' : (string) $mtime)),
        );
    }
}
