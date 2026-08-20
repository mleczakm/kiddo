<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AgeRange;
use App\Entity\File;
use App\Entity\LessonMetadata;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class LessonMetadataWorkshopFileTest extends TestCase
{
    public function testAddFileAttachesWorkshopFileToCollection(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        $file = $this->createFile('materialy.pdf');
        $workshopFile = new WorkshopFile($metadata, $file, WorkshopFileRole::ATTACHMENT, 0);

        static::assertCount(1, $metadata->files);
        static::assertTrue($metadata->files->contains($workshopFile));
    }

    public function testAddFileIsIdempotentForAnAlreadyAttachedFile(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        $file = $this->createFile('materialy.pdf');
        $workshopFile = new WorkshopFile($metadata, $file, WorkshopFileRole::ATTACHMENT, 0);

        $metadata->addFile($workshopFile);

        static::assertCount(1, $metadata->files);
    }

    public function testAddFileRejectsFileBelongingToDifferentMetadata(): void
    {
        $metadataA = $this->createMetadata('A');
        $metadataB = $this->createMetadata('B');
        $file = $this->createFile('materialy.pdf');
        $workshopFile = new WorkshopFile($metadataA, $file, WorkshopFileRole::ATTACHMENT, 0);

        $this->expectException(\InvalidArgumentException::class);
        $metadataB->addFile($workshopFile);
    }

    public function testAddFileRejectsSecondTermsOfUseAttachment(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        new WorkshopFile($metadata, $this->createFile('regulamin.pdf'), WorkshopFileRole::TERMS_OF_USE, 0);

        $this->expectException(\DomainException::class);
        new WorkshopFile($metadata, $this->createFile('regulamin-2.pdf'), WorkshopFileRole::TERMS_OF_USE, 1);
    }

    public function testRemoveFileDetachesItFromCollection(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        $workshopFile = new WorkshopFile(
            $metadata,
            $this->createFile('materialy.pdf'),
            WorkshopFileRole::ATTACHMENT,
            0,
        );

        $metadata->removeFile($workshopFile);

        static::assertCount(0, $metadata->files);
    }

    public function testGetTermsAttachmentReturnsNullWhenNoneAttached(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        new WorkshopFile($metadata, $this->createFile('materialy.pdf'), WorkshopFileRole::ATTACHMENT, 0);

        static::assertNull($metadata->getTermsAttachment());
    }

    public function testGetTermsAttachmentReturnsTheAttachedTermsFile(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        $termsFile = new WorkshopFile($metadata, $this->createFile('regulamin.pdf'), WorkshopFileRole::TERMS_OF_USE, 0);

        static::assertSame($termsFile, $metadata->getTermsAttachment());
    }

    public function testWithTitleCopiesAttachmentsToTheDuplicatedMetadata(): void
    {
        $metadata = $this->createMetadata('Senso Bobasy');
        $file = $this->createFile('regulamin.pdf');
        $original = new WorkshopFile($metadata, $file, WorkshopFileRole::TERMS_OF_USE, 0, 'Ważny od 2026');

        $duplicated = $metadata->withTitle('Bałaganki');

        static::assertCount(1, $duplicated->files);
        $copy = $duplicated->files->first();
        static::assertInstanceOf(WorkshopFile::class, $copy);
        static::assertNotSame($original, $copy);
        static::assertSame($duplicated, $copy->getMetadata());
        static::assertSame($file, $copy->getFile());
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $copy->getRole());
        static::assertSame('Ważny od 2026', $copy->getCaption());

        // The original is untouched by duplication.
        static::assertCount(1, $metadata->files);
        static::assertSame($original, $metadata->files->first());
    }

    private function createFile(string $name): File
    {
        return new File($name, 'application/pdf', 1000, str_repeat('a', 64), base64_encode('content'));
    }

    private function createMetadata(string $title): LessonMetadata
    {
        return new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: '#fff',
            description: 'Description',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(0, 3),
            category: 'test',
        );
    }
}
