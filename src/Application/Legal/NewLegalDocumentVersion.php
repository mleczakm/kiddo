<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Entity\LegalDocumentType;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** A request to publish one new version of a managed legal document. */
final readonly class NewLegalDocumentVersion
{
    public function __construct(
        public LegalDocumentType $type,
        public UploadedFile $upload,
        public \DateTimeImmutable $effectiveFrom,
        public User $publishedBy,
        public ?string $changeSummary = null,
    ) {}
}
