<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FileTest extends TestCase
{
    public function testKeepsIntrinsicFileMetadata(): void
    {
        $uploader = new User('uploader@example.com', 'Uploader');
        $checksum = hash('sha256', 'content');
        $file = new File('guide.pdf', 'application/pdf', 7, $checksum, base64_encode('content'));
        $file->setUploadedBy($uploader);

        static::assertSame('guide.pdf', $file->getOriginalName());
        static::assertSame('application/pdf', $file->getMimeType());
        static::assertSame(7, $file->getSize());
        static::assertSame($checksum, $file->getChecksum());
        static::assertSame($uploader, $file->getUploadedBy());
    }

    public function testRejectsInvalidChecksum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new File('guide.pdf', 'application/pdf', 7, 'invalid', base64_encode('content'));
    }

    public function testRejectsNegativeSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new File('guide.pdf', 'application/pdf', -1, hash('sha256', 'content'), base64_encode('content'));
    }
}
