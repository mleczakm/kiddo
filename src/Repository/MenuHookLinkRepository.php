<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuHookLink;
use App\Entity\MenuHookLinkTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuHookLink>
 */
final class MenuHookLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuHookLink::class);
    }

    /**
     * @return list<MenuHookLink>
     * @throws \UnexpectedValueException
     */
    public function findActiveForSlot(string $slotKey): array
    {
        return $this->findBy(['slotKey' => $slotKey, 'active' => true], ['position' => 'ASC']);
    }

    /**
     * @return list<MenuHookLink>
     * @throws \UnexpectedValueException
     */
    public function findForPostSlug(string $slug): array
    {
        return $this->findBy(['targetType' => MenuHookLinkTarget::POST, 'target' => $slug]);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function nextPositionForSlot(string $slotKey): int
    {
        $maxPosition = $this
            ->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->where('m.slotKey = :slot')
            ->setParameter('slot', $slotKey)
            ->getQuery()
            ->getSingleScalarResult() ?? -1;

        return (int) $maxPosition + 1;
    }
}
