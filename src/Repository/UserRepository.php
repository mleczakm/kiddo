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
}
