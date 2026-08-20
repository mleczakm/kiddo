<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Application\Repository\FileRepositoryInterface;
use App\Entity\File;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Resolves the single stored copy of the static workshop terms-of-use PDF
 * (public/docs/Regulamin.pdf), storing it once (deduped by checksum) rather
 * than once per workshop it gets attached to.
 */
final readonly class WorkshopTermsPdfProvider
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FileStorageInterface $fileStorage,
        #[Autowire('%kernel.project_dir%/public/docs/Regulamin.pdf')]
        private string $termsPdfPath,
    ) {}

    public function exists(): bool
    {
        return is_file($this->termsPdfPath);
    }

    public function getPath(): string
    {
        return $this->termsPdfPath;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    public function findOrStore(): File
    {
        $content = file_get_contents($this->termsPdfPath);
        if ($content === false) {
            throw new \InvalidArgumentException("Unable to read {$this->termsPdfPath}.");
        }

        $checksum = hash('sha256', $content);
        $existing = $this->fileRepository->findOneBy(['checksum' => $checksum]);
        if ($existing !== null) {
            return $existing;
        }

        $upload = new UploadedFile($this->termsPdfPath, 'Regulamin.pdf', 'application/pdf', null, true);

        return $this->fileStorage->store($upload, new FileUploadPolicy('article_document'));
    }
}
