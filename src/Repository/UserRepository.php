<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return User[]
     */
    public function findByRole(string $role): array
    {
        /** @var User[] $users */
        $users = $this->createQueryBuilder('u')
            ->andWhere('JSONB_CONTAINS(u.roles, :role) = true')
            ->setParameter('role', '"' . $role . '"') // Wrap the role in quotes to make it a valid JSON string
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return User[]
     */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var User[] $users */
        $users = $this->createQueryBuilder('u')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return User[]
     */
    public function findAllMatching(string $query): array
    {
        $qb = $this->createQueryBuilder('u');

        if (! empty($query)) {
            $qb->andWhere('LOWER(u.name) LIKE :query OR LOWER(u.email) LIKE :query')
                ->setParameter('query', '%' . strtolower($query) . '%');
        }

        /** @var User[] $result */
        $result = $qb->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Search across id, name, email, and the name of any of the user's
     * children — so a parent can be found by their child's name. Shared
     * by every "pick a user" UI (admin/host assignment, workshop
     * instructors, manual bookings) so the search behavior stays
     * consistent in one place.
     *
     * @return User[]
     */
    public function findForAutocomplete(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.children', 'c')
            ->andWhere('LOWER(u.name) LIKE :query OR LOWER(u.email) LIKE :query OR LOWER(c.name) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->groupBy('u.id')
            ->orderBy('u.name', 'ASC')
            ->setMaxResults($limit);

        if (ctype_digit($query)) {
            $qb->orWhere('u.id = :id')
                ->setParameter('id', (int) $query);
        }

        /** @var User[] $result */
        $result = $qb->getQuery()
            ->getResult();

        return $result;
    }
}
