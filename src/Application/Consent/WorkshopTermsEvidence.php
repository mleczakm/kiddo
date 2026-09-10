<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\WorkshopFile;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class WorkshopTermsEvidence
{
    private function __construct(
        public string $documentRef,
        public string $checksum,
        public string $title,
        public string $fileId,
        public string $fileName,
    ) {}

    public static function fromWorkshopFile(WorkshopFile $terms, string $title): self
    {
        $file = $terms->getFile();

        return new self(
            documentRef: sprintf('workshop_file:%s', $terms->getId()),
            checksum: $file->getChecksum(),
            title: $title,
            fileId: (string) $file->getId(),
            fileName: $file->getOriginalName(),
        );
    }
}
