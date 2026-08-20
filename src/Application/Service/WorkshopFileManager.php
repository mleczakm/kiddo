<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\File;
use App\Entity\LessonMetadata;
use App\Entity\User;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class WorkshopFileManager
{
    public function __construct(
        private FileStorageInterface $fileStorage,
    ) {}

    /**
     * @param array<string, string> $roles Roles keyed by the reusable File id.
     * @param array<string, string> $captions Captions keyed by the reusable File id.
     * @param list<string> $removedFileIds
     */
    public function reconcile(LessonMetadata $metadata, array $roles, array $captions, array $removedFileIds): void
    {
        foreach ($metadata->files->toArray() as $workshopFile) {
            $fileId = (string) $workshopFile->getFile()->getId();
            if (\in_array($fileId, $removedFileIds, true)) {
                $metadata->removeFile($workshopFile);
            }
        }

        $position = 0;
        foreach ($metadata->files as $workshopFile) {
            $fileId = (string) $workshopFile->getFile()->getId();
            $workshopFile->setRole(WorkshopFileRole::from($roles[$fileId] ?? $workshopFile->getRole()->value));
            $workshopFile->setCaption($captions[$fileId] ?? $workshopFile->getCaption());
            $workshopFile->setPosition($position++);
        }
    }

    /**
     * Attach a file that's already stored (e.g. reused from another
     * workshop, or from the media library) without re-uploading it.
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function attachExisting(
        LessonMetadata $metadata,
        File $file,
        WorkshopFileRole $role,
        int $position,
    ): WorkshopFile {
        return new WorkshopFile($metadata, $file, $role, $position);
    }

    /**
     * @param list<UploadedFile> $uploads
     */
    public function attachUploads(
        LessonMetadata $metadata,
        array $uploads,
        WorkshopFileRole $role,
        ?User $uploadedBy,
    ): void {
        foreach ($uploads as $upload) {
            $file = $this->fileStorage->store(
                $upload,
                new FileUploadPolicy($this->policyUsageFor($upload)),
                $uploadedBy,
            );
            new WorkshopFile($metadata, $file, $role, $metadata->files->count());
        }
    }

    private function policyUsageFor(UploadedFile $upload): string
    {
        $realPath = $upload->getRealPath();
        $mimeType = $realPath !== false ? mime_content_type($realPath) : false;
        $mimeType = $mimeType !== false && $mimeType !== '' ? $mimeType : $upload->getClientMimeType();

        return match (true) {
            str_starts_with($mimeType, 'video/') => 'article_video',
            str_starts_with($mimeType, 'image/') => 'article_image',
            default => 'article_document',
        };
    }
}
