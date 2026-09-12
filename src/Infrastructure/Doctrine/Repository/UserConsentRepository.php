<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\UserConsentRepositoryInterface;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Entity\UserConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserConsent> */
final class UserConsentRepository extends ServiceEntityRepository implements UserConsentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserConsent::class);
    }

    #[\Override]
    public function findLatestActive(
        User $user,
        ConsentType $type,
        ?LegalDocumentType $documentType = null,
    ): ?UserConsent {
        $queryBuilder = $this
            ->createQueryBuilder('consent')
            ->andWhere('consent.user = :user')
            ->andWhere('consent.type = :type')
            ->andWhere('consent.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('consent.grantedAt', 'DESC')
            ->addOrderBy('consent.id', 'DESC')
            ->setMaxResults(1);

        if ($documentType !== null) {
            $queryBuilder->andWhere('consent.evidence.documentType = :documentType')->setParameter(
                'documentType',
                $documentType,
            );
        }

        /** @var list<UserConsent> $consents */
        $consents = $queryBuilder->getQuery()->getResult();

        return $consents[0] ?? null;
    }

    /**
     * @return list<UserConsent>
     * @throws \UnexpectedValueException
     */
    #[\Override]
    public function findActiveByType(User $user, ConsentType $type): array
    {
        return $this->findBy([
            'user' => $user,
            'type' => $type,
            'revokedAt' => null,
        ]);
    }

    /**
     * @return list<UserConsent>
     * @throws \UnexpectedValueException
     */
    #[\Override]
    public function findHistoryForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['grantedAt' => 'DESC', 'id' => 'DESC']);
    }
}
