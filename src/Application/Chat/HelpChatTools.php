<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Help\DocArticle;
use App\Application\Help\KnowledgeBase;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Read-only access to the built-in knowledge base (help & documentation centre,
 * {@see \App\UserInterface\Http\Admin\HelpController}) for the chat assistant.
 *
 * Staff tier only — a parent or a guest never sees these tools — and, exactly as
 * on the web centre, every article is additionally gated by the ROLE_* it
 * declares (see {@see KnowledgeBase}). A plain instructor therefore never reads
 * an admin-only article through the chat. The whole feature follows the
 * {@code help_center} flag.
 */
#[AutoconfigureTag('app.chat_tool_provider')]
final readonly class HelpChatTools implements ChatToolProviderInterface
{
    private const int DEFAULT_LIMIT = 5;

    private const int MAX_LIMIT = 15;

    public function __construct(
        private KnowledgeBase $knowledgeBase,
        private FeatureManager $featureManager,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function definitions(): array
    {
        return [
            new ToolDefinition(
                'staff.search_help',
                'Search the built-in knowledge base (help & documentation centre, /admin/pomoc). '
                . 'Fuzzy match over article titles, keywords, summaries and sections — query "przelewy" '
                . 'or "zgoda AI" or "role". Returns article slugs, titles, sections and summaries; pass a '
                . 'slug to staff.get_help_article for the full text. Use it to answer "jak zrobić X w '
                . 'panelu" / "gdzie ustawię Y" instead of guessing. Only articles the caller\'s role '
                . 'grants are returned.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Free-text fragment, e.g. "przelewy", "zgoda AI", "role".',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Max articles to return (default 5, max 15).',
                        ],
                    ],
                    'required' => ['query'],
                ],
                requiresHost: true,
            ),
            new ToolDefinition(
                'staff.get_help_article',
                'Return one knowledge-base article as plain text, with panel links already resolved. '
                . 'Pass the slug from staff.search_help. Fails if the article does not exist or the '
                . 'caller\'s role does not grant access to it.',
                [
                    'type' => 'object',
                    'properties' => [
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Article slug, e.g. "role-i-uprawnienia".',
                        ],
                    ],
                    'required' => ['slug'],
                ],
                requiresHost: true,
            ),
        ];
    }

    #[\Override]
    public function supports(string $name): bool
    {
        return $name === 'staff.search_help' || $name === 'staff.get_help_article';
    }

    #[\Override]
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        if (!$this->featureManager->isEnabled('help_center')) {
            return ToolResult::failure('Knowledge base is disabled.', 'Baza wiedzy jest obecnie wyłączona.');
        }

        try {
            $args = new ToolArguments($arguments);

            return match ($name) {
                'staff.search_help' => $this->search($actor, $args),
                'staff.get_help_article' => $this->getArticle($actor, $args),
                default => ToolResult::failure(sprintf('Unknown help tool: %s', $name)),
            };
        } catch (\InvalidArgumentException $e) {
            return ToolResult::failure($e->getMessage());
        } catch (\RuntimeException $e) {
            $this->logger->error('Knowledge base unavailable for chat', ['exception' => $e]);

            return ToolResult::failure(
                'Knowledge base is temporarily unavailable.',
                'Baza wiedzy jest chwilowo niedostępna.',
            );
        }
    }

    /**
     * @throws \RuntimeException on malformed article frontmatter — caught in {@see call()}
     */
    private function search(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $query = $args->requireString('query');
        $limit = max(1, min($args->int('limit', self::DEFAULT_LIMIT) ?? self::DEFAULT_LIMIT, self::MAX_LIMIT));

        $items = array_map($this->row(...), $this->knowledgeBase->search($actor->roles, $query, $limit));

        return ToolResult::success(sprintf('Znaleziono %d artykułów w bazie wiedzy dla „%s”.', count($items), $query), [
            'query' => $query,
            'articles' => $items,
        ]);
    }

    /**
     * @throws \RuntimeException on malformed article frontmatter — caught in {@see call()}
     */
    private function getArticle(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $article = $this->knowledgeBase->get($actor->roles, $args->requireString('slug'));
        if (!$article instanceof DocArticle) {
            return ToolResult::failure(
                sprintf('No accessible knowledge-base article "%s".', $args->requireString('slug')),
                'Nie znalazłem takiego artykułu w bazie wiedzy albo nie masz do niego dostępu.',
            );
        }

        try {
            $body = $this->knowledgeBase->renderPlainText($article);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to render knowledge-base article for chat', [
                'slug' => $article->slug,
                'exception' => $e,
            ]);

            return ToolResult::failure(
                sprintf('Could not render article "%s".', $article->slug),
                'Nie udało się wczytać treści tego artykułu.',
            );
        }

        return ToolResult::success(sprintf('%s — %s.', $article->title, $article->section->label()), [
            ...$this->row($article),
            'body' => $body,
            'related' => $this->knowledgeBase->filterReadableSlugs($actor->roles, $article->related),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(DocArticle $article): array
    {
        return [
            'slug' => $article->slug,
            'title' => $article->title,
            'section' => $article->section->label(),
            'summary' => $article->summary,
            'primary_route' => $article->primaryRoute,
            'updated_at' => $article->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
