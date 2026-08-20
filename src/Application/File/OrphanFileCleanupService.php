<?php

declare(strict_types=1);

namespace App\Application\File;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;

/**
 * Cleans up orphaned files (unreferenced by any PostFile) older than a safety window.
 * Prevents indefinite growth from abandoned inline uploads and edit cancellations.
 */
final readonly class OrphanFileCleanupService
{
    /** Files older than this are eligible for removal (in hours). Default: 24 hours */
    private int $minAgeHours;

    public function __construct(
        private EntityManagerInterface $em,
        ?int $minAgeHours = null,
    ) {
        $this->minAgeHours = $minAgeHours ?? 24;
    }

    /**
     * Delete orphaned files older than minAgeHours.
     *
     * @return int Number of files deleted
     */
    public function cleanup(): int
    {
        $cutoff = Clock::get()->now()->modify("-{$this->minAgeHours} hours");

        $result = $this->em
            ->createQueryBuilder()
            ->delete('App\Entity\File', 'f')
            ->where('f.id NOT IN (
                SELECT DISTINCT IDENTITY(pf.file)
                FROM App\Entity\PostFile pf
            )')
            ->andWhere('f.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        return (int) $result;
    }

    /**
     * Count orphaned files (for diagnostics/monitoring).
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countOrphans(): int
    {
        return (int) $this->em
            ->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Entity\File', 'f')
            ->where('f.id NOT IN (
                SELECT DISTINCT IDENTITY(pf.file)
                FROM App\Entity\PostFile pf
            )')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count files eligible for cleanup (orphaned + old enough).
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countEligibleForCleanup(): int
    {
        $cutoff = Clock::get()->now()->modify("-{$this->minAgeHours} hours");

        return (int) $this->em
            ->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Entity\File', 'f')
            ->where('f.id NOT IN (
                SELECT DISTINCT IDENTITY(pf.file)
                FROM App\Entity\PostFile pf
            )')
            ->andWhere('f.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
