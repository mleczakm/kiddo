<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\Service\WorkshopFileManager;
use App\Entity\File;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Tests\Assembler\LessonMetadataAssembler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class WorkshopFileManagerTest extends TestCase
{
    public function testReconcileRemovesFilesMarkedForRemoval(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $file1 = $this->createFile('a.pdf');
        $file2 = $this->createFile('b.pdf');
        new WorkshopFile($metadata, $file1, WorkshopFileRole::ATTACHMENT, 0);
        $keptFile = new WorkshopFile($metadata, $file2, WorkshopFileRole::ATTACHMENT, 1);

        $manager = new WorkshopFileManager($this->createMockFileStorage());
        $manager->reconcile($metadata, [], [], [(string) $file1->getId()]);

        static::assertCount(1, $metadata->files);
        static::assertTrue($metadata->files->contains($keptFile));
    }

    public function testReconcileUpdatesRoleAndCaptionKeyedByFileId(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $file = $this->createFile('regulamin.pdf');
        $workshopFile = new WorkshopFile($metadata, $file, WorkshopFileRole::ATTACHMENT, 0);

        $manager = new WorkshopFileManager($this->createMockFileStorage());
        $manager->reconcile(
            $metadata,
            [(string) $file->getId() => 'terms_of_use'],
            [(string) $file->getId() => 'Regulamin obowiązujący od 2026'],
            [],
        );

        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
        static::assertSame('Regulamin obowiązujący od 2026', $workshopFile->getCaption());
    }

    public function testReconcileLeavesUnsubmittedFilesUntouched(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $file = $this->createFile('a.pdf');
        $workshopFile = new WorkshopFile($metadata, $file, WorkshopFileRole::ATTACHMENT, 0, 'Original caption');

        $manager = new WorkshopFileManager($this->createMockFileStorage());
        $manager->reconcile($metadata, [], [], []);

        static::assertSame(WorkshopFileRole::ATTACHMENT, $workshopFile->getRole());
        static::assertSame('Original caption', $workshopFile->getCaption());
    }

    public function testReconcileRejectsAssigningTermsOfUseRoleToASecondFile(): void
    {
        $metadata = LessonMetadataAssembler::new()->assemble();
        $file1 = $this->createFile('a.pdf');
        $file2 = $this->createFile('b.pdf');
        new WorkshopFile($metadata, $file1, WorkshopFileRole::TERMS_OF_USE, 0);
        new WorkshopFile($metadata, $file2, WorkshopFileRole::ATTACHMENT, 1);

        $manager = new WorkshopFileManager($this->createMockFileStorage());

        $this->expectException(\DomainException::class);
        $manager->reconcile($metadata, [(string) $file2->getId() => 'terms_of_use'], [], []);
    }

    private function createFile(string $name): File
    {
        return new File($name, 'application/pdf', 1000, str_repeat('a', 64), base64_encode('content'));
    }

    private function createMockFileStorage(): FileStorageInterface&MockObject
    {
        return $this->createMock(FileStorageInterface::class);
    }
}
