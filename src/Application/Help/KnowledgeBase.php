<?php

declare(strict_types=1);

namespace App\Application\Help;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Role-aware read access to the built-in knowledge base (the help &
 * documentation centre). Every article declares a ROLE_*; a caller sees it only
 * when that role is reachable from their own roles through the firewall's
 * {@see RoleHierarchyInterface} — the same gate
 * {@see \App\UserInterface\Http\Admin\HelpController} applies with {@code isGranted()}.
 *
 * Used by the chat assistant ({@see \App\Application\Chat\HelpChatTools}); the
 * global-search index has its own token-based gate.
 */
final readonly class KnowledgeBase
{
    public function __construct(
        private DocRegistry $registry,
        private DocArticleMatcher $matcher,
        private HelpArticleRenderer $renderer,
        private RoleHierarchyInterface $roleHierarchy,
    ) {}

    /**
     * Articles matching $query, best first, capped at $limit.
     *
     * @param list<string> $roles the caller's (unexpanded) roles
     *
     * @return list<DocArticle>
     *
     * @throws \RuntimeException when an article template has malformed frontmatter
     */
    public function search(array $roles, string $query, int $limit): array
    {
        $scored = [];
        foreach ($this->readableArticles($roles) as $article) {
            $score = $this->matcher->score($article, $query);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'article' => $article];
            }
        }
        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn(array $row): DocArticle => $row['article'], array_slice($scored, 0, max(1, $limit)));
    }

    /**
     * One article by slug, or null when it does not exist or the caller's roles
     * do not grant it (the two are deliberately indistinguishable to the caller).
     *
     * @param list<string> $roles the caller's (unexpanded) roles
     *
     * @throws \RuntimeException when an article template has malformed frontmatter
     */
    public function get(array $roles, string $slug): ?DocArticle
    {
        $slug = mb_strtolower(trim($slug));
        foreach ($this->readableArticles($roles) as $article) {
            if ($article->slug === $slug) {
                return $article;
            }
        }

        return null;
    }

    /**
     * Keeps only the slugs the caller may read — used to filter an article's
     * "related" cross-links.
     *
     * @param list<string> $roles the caller's (unexpanded) roles
     * @param list<string> $slugs
     *
     * @return list<string>
     *
     * @throws \RuntimeException when an article template has malformed frontmatter
     */
    public function filterReadableSlugs(array $roles, array $slugs): array
    {
        $readable = array_map(static fn(DocArticle $a): string => $a->slug, $this->readableArticles($roles));

        return array_values(array_filter($slugs, static fn(string $slug): bool => in_array($slug, $readable, true)));
    }

    public function renderPlainText(DocArticle $article): string
    {
        return $this->renderer->toPlainText($article);
    }

    /**
     * @param list<string> $roles
     *
     * @return list<DocArticle>
     *
     * @throws \RuntimeException when an article template has malformed frontmatter
     */
    private function readableArticles(array $roles): array
    {
        $reachable = $this->roleHierarchy->getReachableRoleNames($roles);

        return array_values(array_filter($this->registry->all(), static fn(DocArticle $article): bool => in_array(
            $article->role,
            $reachable,
            true,
        )));
    }
}
