<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<LegalDocumentVersion> */
final class LegalDocumentVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegalDocumentVersion::class);
    }

    public function findCurrent(LegalDocumentType $type, \DateTimeImmutable $now): ?LegalDocumentVersion
    {
        /** @var list<LegalDocumentVersion> $versions */
        $versions = $this
            ->publicQuery($type, $now)
            ->orderBy('version.effectiveFrom', 'DESC')
            ->addOrderBy('version.version', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $versions[0] ?? null;
    }

    public function findPublicVersion(LegalDocumentType $type, Ulid $id, \DateTimeImmutable $now): ?LegalDocumentVersion
    {
        /** @var list<LegalDocumentVersion> $versions */
        $versions = $this
            ->publicQuery($type, $now)
            ->andWhere('version.id = :id')
            ->setParameter('id', $id, 'ulid')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $versions[0] ?? null;
    }

    /** @return list<LegalDocumentVersion> */
    public function findPublicVersions(LegalDocumentType $type, \DateTimeImmutable $now): array
    {
        /** @var list<LegalDocumentVersion> */
        return $this
            ->publicQuery($type, $now)
            ->orderBy('version.effectiveFrom', 'DESC')
            ->addOrderBy('version.version', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LegalDocumentVersion>
     * @throws \UnexpectedValueException
     */
    public function findAllForDocument(LegalDocument $document): array
    {
        return $this->findBy(['document' => $document], ['version' => 'DESC']);
    }

    private function publicQuery(LegalDocumentType $type, \DateTimeImmutable $now): \Doctrine\ORM\QueryBuilder
    {
        return $this
            ->createQueryBuilder('version')
            ->join('version.document', 'document')
            ->andWhere('document.type = :type')
            ->andWhere('version.publishedAt <= :now')
            ->andWhere('version.effectiveFrom <= :now')
            ->setParameter('type', $type)
            ->setParameter('now', $now);
    }
}
