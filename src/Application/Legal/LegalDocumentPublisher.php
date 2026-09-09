<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LegalDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class LegalDocumentPublisher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileStorageInterface $fileStorage,
        private LegalDocumentRepository $documentRepository,
    ) {}

    /** @throws \InvalidArgumentException */
    public function publish(
        LegalDocumentType $type,
        UploadedFile $upload,
        \DateTimeImmutable $effectiveFrom,
        User $publishedBy,
        ?string $changeSummary = null,
    ): LegalDocumentVersion {
        $changeSummary = $this->normalizeChangeSummary($changeSummary);
        $file = $this->fileStorage->store($upload, new FileUploadPolicy('legal_document'), $publishedBy);

        $document = $this->documentRepository->findOneByType($type);
        if ($document === null) {
            $document = new LegalDocument($type);
            $this->entityManager->persist($document);
        }

        $version = new LegalDocumentVersion($document, $file, $effectiveFrom, $publishedBy, $changeSummary);
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $version;
    }

    /** @throws \InvalidArgumentException */
    private function normalizeChangeSummary(?string $changeSummary): ?string
    {
        $changeSummary = $changeSummary === null ? null : trim($changeSummary);
        if ($changeSummary === '') {
            return null;
        }
        if ($changeSummary !== null && mb_strlen($changeSummary) > 1000) {
            throw new \InvalidArgumentException('A legal document change summary cannot exceed 1000 characters.');
        }

        return $changeSummary;
    }
}
