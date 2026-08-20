<?php

declare(strict_types=1);

namespace App\Application\Repository;

/**
 * @template T of object
 */
interface RepositoryInterface
{
    /** @return T|null */
    public function find(mixed $id, ?int $lockMode = null, ?int $lockVersion = null): ?object;

    /** @return list<T> */
    public function findAll(): array;

    /**
     * @param array<string, mixed>      $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return list<T>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * @param array<string, mixed>      $criteria
     * @param array<string, string>|null $orderBy
     *
     * @return T|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object;
}
