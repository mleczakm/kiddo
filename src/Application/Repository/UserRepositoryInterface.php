<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\User;

/**
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /** @return User[] */
    public function findByRole(string $role): array;

    /** @return User[] */
    public function findCreatedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): array;

    /** @return User[] */
    public function findAllMatching(string $query): array;

    /** @return User[] */
    public function findForAutocomplete(string $query, int $limit = 10): array;

    /** @return list<User> */
    public function findByEmailDomain(string $domain): array;
}
