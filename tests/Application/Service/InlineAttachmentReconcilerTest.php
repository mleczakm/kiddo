<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\InlineAttachmentReconciler;
use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class InlineAttachmentReconcilerTest extends TestCase
{
    public function testRemovesInlineFileNoLongerReferencedInContent(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $file = new File('inline.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        new PostFile($post, $file, PostFileRole::INLINE, 0);

        static::assertCount(1, $post->files);

        $reconciler = new InlineAttachmentReconciler($this->createMockEntityManager());
        $reconciler->reconcile($post, [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'No image here.']]],
            ],
        ]);

        static::assertCount(0, $post->files);
    }

    public function testKeepsInlineFileStillReferencedAsPlainImageNode(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $file = new File('inline.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        new PostFile($post, $file, PostFileRole::INLINE, 0);

        $reconciler = new InlineAttachmentReconciler($this->createMockEntityManager());
        $reconciler->reconcile($post, [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['src' => '/pliki/' . (string) $file->getId() . '/inline.jpg']],
            ],
        ]);

        static::assertCount(1, $post->files);
    }

    /**
     * The captioned figure node (assets/tiptap/article-figure.js) stores its
     * image as an `articleFigure` node, not `image` — the reconciler must
     * recognize both, or every figure's file gets silently orphaned (and
     * eventually deleted by the cleanup command) on the very next save.
     */
    public function testKeepsInlineFileStillReferencedAsArticleFigureNode(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $file = new File('figure.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        new PostFile($post, $file, PostFileRole::INLINE, 0);

        $reconciler = new InlineAttachmentReconciler($this->createMockEntityManager());
        $reconciler->reconcile($post, [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'articleFigure',
                    'attrs' => ['src' => '/pliki/' . (string) $file->getId() . '/figure.jpg', 'alt' => ''],
                    'content' => [['type' => 'text', 'text' => 'A caption']],
                ],
            ],
        ]);

        static::assertCount(1, $post->files);
    }

    public function testNeverRemovesNonInlineRoles(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $cover = new File('cover.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test'));
        new PostFile($post, $cover, PostFileRole::COVER, 0);

        $reconciler = new InlineAttachmentReconciler($this->createMockEntityManager());
        $reconciler->reconcile($post, ['type' => 'doc', 'content' => []]);

        static::assertCount(1, $post->files);
    }

    private function createMockEntityManager(): EntityManagerInterface&MockObject
    {
        return $this->createMock(EntityManagerInterface::class);
    }
}
