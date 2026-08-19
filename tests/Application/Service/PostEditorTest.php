<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\PostEditor;
use App\Entity\Post;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

#[Group('unit')]
final class PostEditorTest extends TestCase
{
    public function testNormalizesEditorialFields(): void
    {
        $post = new Post('Old title', new User('author@example.com', 'Author'));
        $editor = new PostEditor($this->createStub(HtmlSanitizerInterface::class));

        $editor->updateEditorial($post, ' New title ', ' Stories ', ' ', ' workshop-slug ');

        static::assertSame('New title', $post->body->getTitle());
        static::assertSame('Stories', $post->body->getEyebrow());
        static::assertNull($post->body->getExcerpt());
        static::assertSame('workshop-slug', $post->body->getLinkedWorkshopSlug());
    }

    public function testPersistsOnlySanitizedHtmlAlongsideJsonSource(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with('<p>Safe</p><script>alert(1)</script>')
            ->willReturn('<p>Safe</p>');
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostEditor($sanitizer);
        $json = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

        $editor->updateContent($post, $json, '<p>Safe</p><script>alert(1)</script>');

        static::assertSame($json, $post->body->getContentJson());
        static::assertSame('<p>Safe</p>', $post->body->getContentHtml());
    }

    public function testRejectsEmptyTitle(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostEditor($this->createStub(HtmlSanitizerInterface::class));
        $this->expectException(\InvalidArgumentException::class);

        $editor->updateEditorial($post, ' ', null, null, null);
    }

    public function testRejectsNonDocumentJson(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $editor = new PostEditor($this->createStub(HtmlSanitizerInterface::class));
        $this->expectException(\InvalidArgumentException::class);

        $editor->updateContent($post, ['type' => 'paragraph'], '<p>Text</p>');
    }
}
