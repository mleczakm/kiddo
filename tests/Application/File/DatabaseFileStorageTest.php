<?php

declare(strict_types=1);

namespace App\Tests\Application\File;

use App\Application\File\DatabaseFileStorage;
use App\Application\File\FileUploadPolicy;
use App\Entity\File;
use App\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('functional')]
final class DatabaseFileStorageTest extends KernelTestCase
{
    /**
     * A real, minimal valid 1x1 JPEG (base64), so the storage layer's finfo-based
     * MIME sniffing (which never trusts a client-declared type) accepts it.
     */
    private const string MINIMAL_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD/AD8//9k=';

    private DatabaseFileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = static::getContainer()->get(DatabaseFileStorage::class);
    }

    public function testStoresUploadedFileWithComputedChecksum(): void
    {
        [$uploadedFile, $bytes] = $this->createUploadedJpeg('text.jpg');
        $policy = new FileUploadPolicy('article_image');

        $file = $this->storage->store($uploadedFile, $policy);

        static::assertInstanceOf(File::class, $file);
        static::assertSame('text.jpg', $file->getOriginalName());
        static::assertSame('image/jpeg', $file->getMimeType());
        static::assertSame(\strlen($bytes), $file->getSize());
        static::assertSame(hash('sha256', $bytes), $file->getChecksum());
    }

    public function testDecodesFileCorrectly(): void
    {
        [$uploadedFile, $bytes] = $this->createUploadedJpeg('test.jpg');
        $policy = new FileUploadPolicy('article_image');

        $file = $this->storage->store($uploadedFile, $policy);
        $decodedContent = $this->storage->read($file);

        static::assertSame($bytes, $decodedContent);
    }

    public function testRejectsInvalidBase64Data(): void
    {
        $file = new File('broken.jpg', 'image/jpeg', 10, str_repeat('a', 64), 'not valid base64 !!!');

        $this->expectException(InvalidArgumentException::class);
        $this->storage->read($file);
    }

    public function testAssociatesUploadedByUser(): void
    {
        [$uploadedFile] = $this->createUploadedJpeg('test.jpg');
        $policy = new FileUploadPolicy('article_image');

        $user = new User('test@example.com', 'Test User');
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        $file = $this->storage->store($uploadedFile, $policy, $user);

        static::assertSame($user->getId(), $file->getUploadedBy()?->getId());
    }

    public function testRejectsFileExceedingSizeLimit(): void
    {
        [$uploadedFile] = $this->createUploadedJpeg('large.jpg');
        $policy = new FileUploadPolicy('article_image', fileSizeLimit: 10);

        $this->expectException(InvalidArgumentException::class);
        $this->storage->store($uploadedFile, $policy);
    }

    public function testRejectsMismatchedContentType(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'this is plain text, not an image');
        $uploadedFile = new UploadedFile($tempFile, 'fake.jpg', 'image/jpeg', filesize($tempFile) ?: null, true);
        $policy = new FileUploadPolicy('article_image');

        $this->expectException(InvalidArgumentException::class);
        $this->storage->store($uploadedFile, $policy);
    }

    /**
     * @return array{0: UploadedFile, 1: string}
     */
    private function createUploadedJpeg(string $filename): array
    {
        $bytes = (string) base64_decode(self::MINIMAL_JPEG_BASE64, true);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.jpg';
        file_put_contents($tempFile, $bytes);

        $uploadedFile = new UploadedFile(
            $tempFile,
            $filename,
            'image/jpeg',
            filesize($tempFile) ?: null,
            true,
        );

        return [$uploadedFile, $bytes];
    }

    private function getEntityManager()
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }
}
