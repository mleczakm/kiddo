<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Service\RecommendationManager;
use App\Application\Service\RecommendationProvider;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.chat_tool_provider')]
final readonly class RecommendationChatTools implements ChatToolProviderInterface
{
    public function __construct(
        private RecommendationProvider $provider,
        private RecommendationManager $manager,
    ) {}

    #[\Override]
    public function definitions(): array
    {
        $confirmation = ['confirm' => ['type' => 'boolean']];
        $id = ['id' => ['type' => 'string', 'description' => 'Recommendation ID from admin.list_recommendations.']];
        return [
            new ToolDefinition(
                'admin.list_recommendations',
                'List real parent recommendations, including unpublished drafts. Search by author or text; optionally filter status.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.save_recommendation',
                'Create or edit a parent recommendation. Pass an existing ID to edit. Drafts are never public; published and enabled recommendations appear on the homepage. Text must be a genuine review supplied by the user.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$id,
                        'author' => ['type' => 'string', 'maxLength' => 200],
                        'text' => ['type' => 'string', 'maxLength' => 10_000],
                        'source' => [
                            'type' => 'string',
                            'enum' => ['google', 'facebook', 'kiddo'],
                            'default' => 'kiddo',
                        ],
                        'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'default' => 'draft'],
                        'enabled' => ['type' => 'boolean', 'default' => true],
                        ...$confirmation,
                    ],
                    'required' => ['author', 'text', 'confirm'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.approve_recommendation',
                'Approve a draft and enable its publication on the homepage.',
                [
                    'type' => 'object',
                    'properties' => [...$id, ...$confirmation],
                    'required' => ['id', 'confirm'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.delete_recommendation',
                'Delete a recommendation, removing it from the homepage and moderation queue.',
                [
                    'type' => 'object',
                    'properties' => [...$id, ...$confirmation],
                    'required' => ['id', 'confirm'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
        ];
    }

    #[\Override]
    public function supports(string $name): bool
    {
        return in_array(
            $name,
            [
                'admin.list_recommendations',
                'admin.save_recommendation',
                'admin.approve_recommendation',
                'admin.delete_recommendation',
            ],
            true,
        );
    }

    #[\Override]
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        if ($actor->isGuest() || !$actor->isAdmin()) {
            return ToolResult::failure('This tool requires an authenticated administrator.');
        }
        if ($name !== 'admin.list_recommendations' && ($arguments['confirm'] ?? false) !== true) {
            return ToolResult::failure('Mutation not confirmed. Requires confirm=true.');
        }
        try {
            $args = new ToolArguments($arguments);
            return match ($name) {
                'admin.list_recommendations' => $this->list($args),
                'admin.save_recommendation' => $this->save($args),
                'admin.approve_recommendation' => $this->moderate($args, 'approve'),
                'admin.delete_recommendation' => $this->moderate($args, 'delete'),
                default => ToolResult::failure('Unknown recommendation tool.'),
            };
        } catch (\InvalidArgumentException $exception) {
            return ToolResult::failure($exception->getMessage());
        }
    }

    private function list(ToolArguments $args): ToolResult
    {
        $status = $args->string('status');
        if ($status !== null && !in_array($status, ['draft', 'published'], true)) {
            return ToolResult::failure('Status must be draft or published.');
        }
        $reviews = $this->provider->search($args->string('query', '') ?? '', $status);
        return ToolResult::success(sprintf('Znaleziono %d opinii.', count($reviews)), [
            'total' => count($reviews),
            'recommendations' => array_slice($reviews, 0, max(1, min(100, $args->int('limit', 50) ?? 50))),
        ]);
    }

    /** @throws \InvalidArgumentException */
    private function save(ToolArguments $args): ToolResult
    {
        $review = [
            'author' => $args->requireString('author'),
            'text' => $args->requireString('text'),
            'source' => $args->string('source', 'kiddo'),
            'status' => $args->string('status', 'draft'),
            'enabled' => $args->bool('enabled', true),
        ];
        if ($args->has('id')) {
            $review['id'] = $args->requireString('id');
        }
        return ToolResult::success('Opinia została zapisana.', ['id' => $this->manager->upsert($review)]);
    }

    /** @throws \InvalidArgumentException */
    private function moderate(ToolArguments $args, string $action): ToolResult
    {
        $id = $args->requireString('id');
        $this->manager->moderate($id, $action);
        return ToolResult::success(
            $action === 'delete' ? 'Opinia została usunięta.' : 'Opinia została zatwierdzona i opublikowana.',
            ['id' => $id],
        );
    }
}
