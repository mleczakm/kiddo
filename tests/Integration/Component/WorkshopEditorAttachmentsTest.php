<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\File;
use App\Entity\Lesson;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\WorkshopEditorComponent;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group('functional')]
final class WorkshopEditorAttachmentsTest extends WorkshopEditorComponentTestCase
{
    public function testImageUploadIsPersistedWhenSavingExistingLesson(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $imagePath = tempnam(sys_get_temp_dir(), 'workshop-image-');
        static::assertNotFalse($imagePath);
        $imageData = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        static::assertNotFalse($imageData);
        file_put_contents($imagePath, $imageData);

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->call('save', files: [
                'imageFile' => new UploadedFile($imagePath, 'workshop.png', 'image/png', null, true),
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertSame('image/png', $reloaded->getMetadata()->imageMimeType);
            static::assertSame(base64_encode($imageData), $reloaded->getMetadata()->imageData);
        } finally {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    public function testAttachmentUploadIsPersistedWithSelectedRole(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $attachmentPath = tempnam(sys_get_temp_dir(), 'workshop-attachment-');
        static::assertNotFalse($attachmentPath);
        file_put_contents($attachmentPath, '%PDF-1.4 test content');

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->set('newAttachmentRole', 'terms_of_use');
            $component->call('save', files: [
                'attachmentFiles' => [
                    new UploadedFile($attachmentPath, 'materialy.pdf', 'application/pdf', null, true),
                ],
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertCount(1, $reloaded->getMetadata()->files);
            $workshopFile = $reloaded->getMetadata()->files->first();
            static::assertInstanceOf(WorkshopFile::class, $workshopFile);
            static::assertSame('materialy.pdf', $workshopFile->getFile()->getOriginalName());
            static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
        } finally {
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }
    }

    public function testRemovedAttachmentIdIsDetachedOnSave(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $file = new File('materialy.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($file);
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $file->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('removedAttachmentIds', [$fileId]);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertCount(0, $reloaded->getMetadata()->files);
    }

    public function testChangingAttachmentRoleAndCaptionPersistsOnSave(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $file = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($file);
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $file->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('attachmentRoles', [$fileId => 'terms_of_use']);
        $component->set('attachmentCaptions', [$fileId => 'Obowiązuje od 2026']);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        $workshopFile = $reloaded->getMetadata()->files->first();
        static::assertInstanceOf(WorkshopFile::class, $workshopFile);
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
        static::assertSame('Obowiązuje od 2026', $workshopFile->getCaption());
    }

    public function testUploadingASecondTermsOfUseAttachmentIsRejectedWithoutCrashing(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $existingTermsFile = new File(
            'regulamin.pdf',
            'application/pdf',
            100,
            str_repeat('a', 64),
            base64_encode('content'),
        );
        $this->em->persist($existingTermsFile);
        new WorkshopFile($lesson->getMetadata(), $existingTermsFile, WorkshopFileRole::TERMS_OF_USE, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $attachmentPath = tempnam(sys_get_temp_dir(), 'workshop-attachment-');
        static::assertNotFalse($attachmentPath);
        file_put_contents($attachmentPath, '%PDF-1.4 another regulamin');

        try {
            $this->client->loginUser($admin);
            $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
                'lessonId' => $lessonId,
                'startOpen' => false,
            ]);

            $component->set('newAttachmentRole', 'terms_of_use');
            $component->call('save', files: [
                'attachmentFiles' => [
                    new UploadedFile($attachmentPath, 'nowy-regulamin.pdf', 'application/pdf', null, true),
                ],
            ]);

            $this->em->clear();
            $reloaded = $this->em->find(Lesson::class, $lessonId);
            static::assertNotNull($reloaded);
            static::assertCount(1, $reloaded->getMetadata()->files);
            $workshopFile = $reloaded->getMetadata()->files->first();
            static::assertInstanceOf(WorkshopFile::class, $workshopFile);
            static::assertSame('regulamin.pdf', $workshopFile->getFile()->getOriginalName());
        } finally {
            if (is_file($attachmentPath)) {
                unlink($attachmentPath);
            }
        }
    }

    public function testAddLibraryFileStagesAnExistingFileForAttachment(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame([$fileId], $workshopEditorComponent->libraryAttachmentIds);
    }

    public function testSavingWithAStagedLibraryFileAttachesItWithoutStoringItAgain(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('newAttachmentRole', 'terms_of_use');
        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertCount(1, $reloaded->getMetadata()->files);
        $workshopFile = $reloaded->getMetadata()->files->first();
        static::assertInstanceOf(WorkshopFile::class, $workshopFile);
        // Same File row reused — not a fresh upload.
        static::assertSame($fileId, (string) $workshopFile->getFile()->getId());
        static::assertSame(WorkshopFileRole::TERMS_OF_USE, $workshopFile->getRole());
    }

    public function testRemoveLibraryFileUnstagesIt(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $libraryFile = new File('regulamin.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('content'));
        $this->em->persist($libraryFile);
        $this->em->flush();
        $lessonId = $lesson->getId();
        $fileId = (string) $libraryFile->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->call('addLibraryFile', arguments: ['fileId' => $fileId]);
        $component->call('removeLibraryFile', arguments: ['fileId' => $fileId]);

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        static::assertSame([], $workshopEditorComponent->libraryAttachmentIds);
    }

    public function testMediaLibrarySearchExcludesFilesAlreadyAttachedToTheCurrentWorkshop(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $attachedFile = new File('regulamin-a.pdf', 'application/pdf', 100, str_repeat('a', 64), base64_encode('a'));
        $otherFile = new File('regulamin-b.pdf', 'application/pdf', 100, str_repeat('b', 64), base64_encode('b'));
        $this->em->persist($attachedFile);
        $this->em->persist($otherFile);
        new WorkshopFile($lesson->getMetadata(), $attachedFile, WorkshopFileRole::ATTACHMENT, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();

        $this->client->loginUser($admin);
        $component = $this->createLiveComponent(name: 'WorkshopEditor', client: $this->client, data: [
            'lessonId' => $lessonId,
            'startOpen' => false,
        ]);

        $component->set('mediaLibrarySearch', 'regulamin');

        /** @var WorkshopEditorComponent $workshopEditorComponent */
        $workshopEditorComponent = $component->component();
        $resultIds = array_column($workshopEditorComponent->getMediaLibraryResults(), 'id');

        static::assertContains((string) $otherFile->getId(), $resultIds);
        static::assertNotContains((string) $attachedFile->getId(), $resultIds);
    }
}
