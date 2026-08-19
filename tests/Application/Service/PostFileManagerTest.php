<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\PostFileManager;
use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use App\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostFileManagerTest extends TestCase
{
    public function testReconciliationRemovesUnsubmittedAttachments(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $file1 = new File('image1.jpg', 'image/jpeg', 1000, str_repeat('a', 64), base64_encode('test1'));
        $file2 = new File('image2.jpg', 'image/jpeg', 1000, str_repeat('b', 64), base64_encode('test2'));

        $pf1 = new PostFile($post, $file1, PostFileRole::COVER, 0);
        $pf2 = new PostFile($post, $file2, PostFileRole::ATTACHMENT, 1);

        static::assertCount(2, $post->files);

        $manager = new PostFileManager($this->createMockEntityManager());
        $manager->reconcileAttachments($post, [
            [
                'id' => (string) $file1->getId(),
                'role' => 'cover',
                'position' => 0,
                'altText' => 'Alt 1',
                'caption' => null,
                'downloadName' => null,
            ],
        ]);

        static::assertCount(1, $post->files);
    }

    public function testReconciliationRejectsUnknownFileId(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $manager = new PostFileManager($this->createMockEntityManager());

        $this->expectException(InvalidArgumentException::class);
        $manager->reconcileAttachments($post, [
            [
                'id' => 'unknown-id-12345678901234567890',
                'role' => 'cover',
                'position' => 0,
                'altText' => null,
                'caption' => null,
                'downloadName' => null,
            ],
        ]);
    }

    public function testAttachFileCreatesPostFileWithMetadata(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $file = new File('image.jpg', 'image/jpeg', 5000, str_repeat('a', 64), base64_encode('content'));
        $manager = new PostFileManager($this->createMockEntityManager());

        $postFile = $manager->attachFile(
            $post,
            $file,
            PostFileRole::INLINE,
            0,
            'Alt text',
            'A caption',
            'download.jpg',
        );

        static::assertSame($post, $postFile->getPost());
        static::assertSame($file, $postFile->getFile());
        static::assertSame(PostFileRole::INLINE, $postFile->getRole());
        static::assertSame('Alt text', $postFile->getAltText());
        static::assertSame('A caption', $postFile->getCaption());
        static::assertSame('download.jpg', $postFile->getDownloadName());
    }

    private function createMockEntityManager()
    {
        return $this->createMock('Doctrine\ORM\EntityManagerInterface');
    }
}
