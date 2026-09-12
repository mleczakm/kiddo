<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;

/**
 * @extends RepositoryInterface<LegalDocument>
 */
interface LegalDocumentRepositoryInterface extends RepositoryInterface
{
    public function findOneByType(LegalDocumentType $type): ?LegalDocument;
}
