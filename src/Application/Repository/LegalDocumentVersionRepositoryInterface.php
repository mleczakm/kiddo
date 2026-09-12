<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use Symfony\Component\Uid\Ulid;

/**
 * @extends RepositoryInterface<LegalDocumentVersion>
 */
interface LegalDocumentVersionRepositoryInterface extends RepositoryInterface
{
    public function findCurrent(LegalDocumentType $type, \DateTimeImmutable $now): ?LegalDocumentVersion;

    public function findPublicVersion(LegalDocumentType $type, Ulid $id, \DateTimeImmutable $now): ?LegalDocumentVersion;

    /** @return list<LegalDocumentVersion> */
    public function findPublicVersions(LegalDocumentType $type, \DateTimeImmutable $now): array;

    /** @return list<LegalDocumentVersion> */
    public function findAllForDocument(LegalDocument $document): array;
}
