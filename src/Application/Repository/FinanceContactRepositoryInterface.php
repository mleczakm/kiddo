<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\FinanceContact;
use App\Entity\User;

/**
 * @extends RepositoryInterface<FinanceContact>
 */
interface FinanceContactRepositoryInterface extends RepositoryInterface
{
    /** @return list<FinanceContact> */
    #[\Override]
    public function findAll(): array;

    public function existsForUser(User $user): bool;
}
