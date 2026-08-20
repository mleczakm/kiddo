<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\Transfer;

/**
 * @extends RepositoryInterface<Transfer>
 */
interface TransferRepositoryInterface extends RepositoryInterface
{
    /** @return Transfer[] */
    public function findAllWithDeleted(): array;

    /** @return Transfer[] */
    public function findOnlyDeleted(): array;

    /** @return Transfer[] */
    public function findByTitleStartingWith(string $prefix): array;

    public function restore(Transfer $transfer): void;
}
