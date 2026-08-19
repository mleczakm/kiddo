<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\DatabaseFileStorage;
use App\Application\File\FileUploadPolicy;
use App\Entity\File;
use App\Entity\User;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DatabaseFileStorageTest extends KernelTestCase
{
    private DatabaseFileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = static::getContainer()->get(DatabaseFileStorage::class);
    }

    public function testStoresUploadedFileWithComputedChecksum(): void
    {
        $uploadedFile = $this->createUploadedFile('test content', 'text.jpg', 'image/jpeg');
        $policy = new FileUploadPolicy('article_image');

        $file = $this->storage->store($uploadedFile, $policy);

        static::assertInstanceOf(File::class, $file);
        static::assertSame('text.jpg', $file->getOriginalName());
        static::assertSame('image/jpeg', $file->getMimeType());
        static::assertSame(12, $file->getSize());
        static::assertSame(hash('sha256', 'test content'), $file->getChecksum());
    }

    public function testDecodesFileCorrectly(): void
    {
        $originalContent = 'file content for testing';
        $uploadedFile = $this->createUploadedFile($originalContent, 'test.jpg', 'image/jpeg');
        $policy = new FileUploadPolicy('article_image');

        $file = $this->storage->store($uploadedFile, $policy);
        $decodedContent = $this->storage->read($file);

        static::assertSame($originalContent, $decodedContent);
    }

    public function testRejectsInvalidBase64Data(): void
    {
        $file = new File('broken.jpg', 'image/jpeg', 10, 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'not valid base64 !!!');

        $this->expectException(InvalidArgumentException::class);
        $this->storage->read($file);
    }

    public function testAssociatesUploadedByUser(): void
    {
        $uploadedFile = $this->createUploadedFile('test', 'test.jpg', 'image/jpeg');
        $policy = new FileUploadPolicy('article_image');

        $user = new User('test@example.com', 'Test User');
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        $file = $this->storage->store($uploadedFile, $policy, $user);

        static::assertSame($user->getId(), $file->getUploadedBy()?->getId());
    }

    public function testRejectsFileWithPolicyViolation(): void
    {
        $uploadedFile = $this->createUploadedFile(
            str_repeat('x', 10 * 1024 * 1024),
            'large.jpg',
            'image/jpeg',
        );
        $policy = new FileUploadPolicy('article_image');

        $this->expectException(InvalidArgumentException::class);
        $this->storage->store($uploadedFile, $policy);
    }

    private function createUploadedFile(string $content, string $filename, string $mimeType): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            $filename,
            $mimeType,
            filesize($tempFile) ?: null,
            true,
        );
    }

    private function getEntityManager()
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
