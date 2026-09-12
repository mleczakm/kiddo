<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\LegalDocumentRepositoryInterface;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LegalDocument> */
final class LegalDocumentRepository extends ServiceEntityRepository implements LegalDocumentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocument::class);
    }

    #[\Override]
    public function findOneByType(LegalDocumentType $type): ?LegalDocument
    {
        return $this->findOneBy(['type' => $type]);
    }
}
