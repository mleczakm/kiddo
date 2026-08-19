<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[Group('unit')]
final class FileTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

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
        static::assertSame(base64_encode('content'), $file->getData());
        static::assertSame($uploader, $file->getUploadedBy());
    }

    public function testRecordsCreationTimeAndAllowsUploaderToBeCleared(): void
    {
        Clock::set(new MockClock('2026-08-19T10:15:00+02:00'));
        $file = new File('guide.pdf', 'application/pdf', 7, hash('sha256', 'content'), base64_encode('content'));
        $file->setUploadedBy(new User('uploader@example.com', 'Uploader'));
        $file->setUploadedBy(null);

        static::assertSame('2026-08-19T10:15:00+02:00', $file->getCreatedAt()->format(DATE_ATOM));
        static::assertNull($file->getUploadedBy());
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
