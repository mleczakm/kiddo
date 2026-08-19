<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\PostSeoEditor;
use App\Application\Service\PostSeoInput;
use App\Application\Service\PostSocialInput;
use App\Entity\Post;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PostSeoEditorTest extends TestCase
{
    public function testNormalizesBlankFieldsToNull(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostSeoEditor();

        $editor->updateSeo($post, new PostSeoInput(' ', ' ', null, true, false), new PostSocialInput(' ', ' '));

        static::assertNull($post->seo->getSeoTitle());
        static::assertNull($post->seo->getSeoDescription());
        static::assertNull($post->seo->getCanonicalUrl());
        static::assertTrue($post->seo->shouldRobotsIndex());
        static::assertFalse($post->seo->shouldRobotsFollow());
        static::assertNull($post->seo->getSocialTitle());
        static::assertNull($post->seo->getSocialDescription());
    }

    public function testPersistsTrimmedValues(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostSeoEditor();

        $editor->updateSeo(
            $post,
            new PostSeoInput(' SEO title ', ' SEO description ', 'https://kiddo.example/blog/article', false, true),
            new PostSocialInput(' Social title ', ' Social description '),
        );

        static::assertSame('SEO title', $post->seo->getSeoTitle());
        static::assertSame('SEO description', $post->seo->getSeoDescription());
        static::assertSame('https://kiddo.example/blog/article', $post->seo->getCanonicalUrl());
        static::assertFalse($post->seo->shouldRobotsIndex());
        static::assertTrue($post->seo->shouldRobotsFollow());
        static::assertSame('Social title', $post->seo->getSocialTitle());
        static::assertSame('Social description', $post->seo->getSocialDescription());
    }

    #[DataProvider('invalidCanonicalUrls')]
    public function testRejectsInvalidCanonicalUrl(string $canonicalUrl): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostSeoEditor();
        $this->expectException(\InvalidArgumentException::class);

        $editor->updateSeo(
            $post,
            new PostSeoInput(null, null, $canonicalUrl, true, true),
            new PostSocialInput(null, null),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCanonicalUrls(): iterable
    {
        yield 'non-https scheme' => ['http://kiddo.example/blog/article'];
        yield 'relative path' => ['/blog/article'];
    }

    public function testRejectsOverlongSeoTitle(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostSeoEditor();
        $this->expectException(\InvalidArgumentException::class);

        $editor->updateSeo(
            $post,
            new PostSeoInput(str_repeat('a', 71), null, null, true, true),
            new PostSocialInput(null, null),
        );
    }
}
