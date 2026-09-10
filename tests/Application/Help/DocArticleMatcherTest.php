<?php

declare(strict_types=1);

namespace App\Tests\Application\Help;

use App\Application\Help\DocArticle;
use App\Application\Help\DocArticleMatcher;
use App\Application\Help\DocSection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class DocArticleMatcherTest extends TestCase
{
    private DocArticleMatcher $matcher;

    #[\Override]
    protected function setUp(): void
    {
        $this->matcher = new DocArticleMatcher();
    }

    public function testRanksExactTitleAboveKeywordAboveSummaryAboveHaystack(): void
    {
        $article = $this->article(
            title: 'Płatności i przelewy',
            summary: 'Dopasowanie przelewów bankowych do rezerwacji.',
            keywords: ['blik', 'transfer'],
        );

        static::assertSame(6, $this->matcher->score($article, 'płatności i przelewy'));
        static::assertSame(5, $this->matcher->score($article, 'blik'));
        static::assertSame(5, $this->matcher->score($article, 'płatności'));
        static::assertSame(4, $this->matcher->score($article, 'przelewy'));
        static::assertSame(2, $this->matcher->score($article, 'dopasowanie'));
        static::assertSame(0, $this->matcher->score($article, 'newsletter'));
    }

    public function testShortOrBlankQueryNeverMatches(): void
    {
        $article = $this->article(title: 'Newsletter', summary: 'x', keywords: ['n']);

        static::assertSame(0, $this->matcher->score($article, 'n'));
        static::assertSame(0, $this->matcher->score($article, '   '));
    }

    /**
     * @param list<string> $keywords
     */
    private function article(string $title, string $summary, array $keywords): DocArticle
    {
        return new DocArticle(
            slug: 'demo',
            title: $title,
            summary: $summary,
            section: DocSection::Finance,
            role: 'ROLE_MANAGE_PAYMENTS',
            primaryRoute: null,
            keywords: $keywords,
            related: [],
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }
}
