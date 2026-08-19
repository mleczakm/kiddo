<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuHookLink;
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
}
