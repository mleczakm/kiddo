<?php

declare(strict_types=1);

namespace App\Application\File;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileStorageInterface
{
    /**
     * Store an uploaded file: decode base64, compute SHA-256, validate size/type, persist via ORM.
     *
     * @throws \InvalidArgumentException on validation failure
     */
    public function store(UploadedFile $upload, FileUploadPolicy $policy, ?User $uploadedBy = null): File;

    /**
     * Read (decode) the stored file's binary data.
     */
    public function read(File $file): string;

    /**
     * Delete a file record. Orphan cleanup is the caller's responsibility.
     */
    public function delete(File $file): void;
}
