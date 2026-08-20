<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Application\Service\WorkshopFileManager;
use App\Entity\File;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Tests\Assembler\LessonMetadataAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('unit')]
final class WorkshopFileManagerAttachTest extends TestCase
{
    public function testAttachExistingReusesTheGivenFileWithoutStoringAgain(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $file = $this->createFile('regulamin.pdf');

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(static::never())->method('store');

        $manager = new WorkshopFileManager($storage);
        $workshopFile = $manager->attachExisting($metadata, $file, WorkshopFileRole::TERMS_OF_USE, 0);

        static::assertCount(1, $metadata->files);
        static::assertSame($file, $workshopFile->getFile());
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
    }

    public function testAttachExistingRejectsSecondTermsOfUseAttachment(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        new WorkshopFile($metadata, $this->createFile('regulamin.pdf'), WorkshopFileRole::TERMS_OF_USE, 0);

        $manager = new WorkshopFileManager($this->createMockFileStorage());

        $this->expectException(\DomainException::class);
        $manager->attachExisting($metadata, $this->createFile('inny-regulamin.pdf'), WorkshopFileRole::TERMS_OF_USE, 1);
    }

    public function testAttachUploadsStoresFileAndCreatesWorkshopFileWithGivenRole(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $upload = $this->createUploadedFile();
        $storedFile = $this->createFile('uploaded.pdf');

        $storage = $this->createMock(FileStorageInterface::class);
        $storage
            ->expects(static::once())
            ->method('store')
            ->with($upload, static::isInstanceOf(FileUploadPolicy::class), null)
            ->willReturn($storedFile);

        $manager = new WorkshopFileManager($storage);
        $manager->attachUploads($metadata, [$upload], WorkshopFileRole::TERMS_OF_USE, null);

        static::assertCount(1, $metadata->files);
        $workshopFile = $metadata->files->first();
        static::assertInstanceOf(WorkshopFile::class, $workshopFile);
        static::assertSame($storedFile, $workshopFile->getFile());
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
    }

    public function testAttachUploadsRejectsSecondTermsOfUseAttachment(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $existingTermsFile = $this->createFile('regulamin.pdf');
        new WorkshopFile($metadata, $existingTermsFile, WorkshopFileRole::TERMS_OF_USE, 0);

        $upload = $this->createUploadedFile();
        $storedFile = $this->createFile('nowy-regulamin.pdf');
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('store')->willReturn($storedFile);

        $manager = new WorkshopFileManager($storage);

        $this->expectException(\DomainException::class);
        $manager->attachUploads($metadata, [$upload], WorkshopFileRole::TERMS_OF_USE, null);
    }

    private function createFile(string $name): File
    {
        return new File($name, 'application/pdf', 1000, str_repeat('a', 64), base64_encode('content'));
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'workshop-file-');
        static::assertNotFalse($path);
        file_put_contents($path, '%PDF-1.4 test');
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return new UploadedFile($path, 'document.pdf', 'application/pdf', null, true);
    }

    private function createMockFileStorage(): FileStorageInterface&MockObject
    {
        return $this->createMock(FileStorageInterface::class);
    }
}
