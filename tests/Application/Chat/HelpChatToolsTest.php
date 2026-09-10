<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat;

use App\Application\Chat\ChatActor;
use App\Application\Chat\ChatToolRegistry;
use App\Application\Chat\ToolDefinition;
use App\Application\Chat\ToolResult;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The chat assistant may read the built-in knowledge base, but only for staff,
 * and only the articles the caller's role grants — the same gate the web help
 * centre applies.
 */
#[Group('functional')]
final class HelpChatToolsTest extends KernelTestCase
{
    private ChatToolRegistry $registry;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $registry = self::getContainer()->get(ChatToolRegistry::class);
        static::assertInstanceOf(ChatToolRegistry::class, $registry);
        $this->registry = $registry;
    }

    public function testParentNeitherSeesNorCanCallTheKnowledgeBaseTools(): void
    {
        $parent = new ChatActor(UserAssembler::new()->withId(1)->withRoles('ROLE_USER')->assemble(), ['ROLE_USER']);

        $names = array_map(
            static fn(ToolDefinition $definition): string => $definition->name,
            $this->registry->definitions($parent),
        );
        static::assertNotContains('staff.search_help', $names);

        $denied = $this->registry->call('staff.search_help', $parent, [
            'query' => 'przelewy',
        ]);
        static::assertFalse($denied->ok);
        static::assertStringContainsString('prowadzących', $denied->summary);
    }

    public function testHostCanSearchAndGetsStructuredArticleRows(): void
    {
        $host = $this->actor('ROLE_HOST');

        $names = array_map(
            static fn(ToolDefinition $definition): string => $definition->name,
            $this->registry->definitions($host),
        );
        static::assertContains('staff.search_help', $names);

        $result = $this->registry->call('staff.search_help', $host, [
            'query' => 'rezerwacj',
        ]);
        static::assertTrue($result->ok, $result->error ?? '');

        $articles = $this->rows($result, 'articles');
        static::assertNotEmpty($articles);
        static::assertArrayHasKey('slug', $articles[0]);
        static::assertArrayHasKey('summary', $articles[0]);
    }

    public function testSearchHidesArticlesTheRoleMayNotSee(): void
    {
        // "mailing" only appears in dokumenty-publikacja-nowej-wersji (ROLE_SETTINGS).
        static::assertNotContains(
            'dokumenty-publikacja-nowej-wersji',
            array_column($this->searchArticles($this->actor('ROLE_HOST'), 'mailing'), 'slug'),
        );
        static::assertContains(
            'dokumenty-publikacja-nowej-wersji',
            array_column($this->searchArticles($this->actor('ROLE_ADMIN'), 'mailing'), 'slug'),
        );
    }

    public function testGetArticleIsGatedByRoleAndReturnsPlainText(): void
    {
        // role-i-uprawnienia declares ROLE_ADMIN.
        $denied = $this->registry->call('staff.get_help_article', $this->actor('ROLE_HOST'), [
            'slug' => 'role-i-uprawnienia',
        ]);
        static::assertFalse($denied->ok);

        $missing = $this->registry->call('staff.get_help_article', $this->actor('ROLE_ADMIN'), [
            'slug' => 'nie-ma-takiego-artykulu',
        ]);
        static::assertFalse($missing->ok);

        $ok = $this->registry->call('staff.get_help_article', $this->actor('ROLE_ADMIN'), [
            'slug' => 'role-i-uprawnienia',
        ]);
        static::assertTrue($ok->ok, $ok->error ?? '');
        static::assertSame('role-i-uprawnienia', $ok->data['slug'] ?? null);

        $body = $this->stringData($ok, 'body');
        static::assertStringContainsString('ROLE_HOST', $body);
        static::assertStringNotContainsString('<p>', $body);

        static::assertContains('wprowadzenie', $this->rows($ok, 'related'));
    }

    private function actor(string $role): ChatActor
    {
        return new ChatActor(UserAssembler::new()->withId(crc32($role))->withRoles($role)->assemble(), [$role]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchArticles(ChatActor $actor, string $query): array
    {
        $result = $this->registry->call('staff.search_help', $actor, [
            'query' => $query,
        ]);
        static::assertTrue($result->ok, $result->error ?? '');

        return $this->rows($result, 'articles');
    }

    private function stringData(ToolResult $result, string $key): string
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsString($result->data[$key]);

        return $result->data[$key];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(ToolResult $result, string $key): array
    {
        static::assertArrayHasKey($key, $result->data);
        static::assertIsArray($result->data[$key]);

        /** @var list<array<string, mixed>> */
        return $result->data[$key];
    }
}
