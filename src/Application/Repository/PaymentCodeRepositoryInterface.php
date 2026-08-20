<?php

declare(strict_types=1);

namespace App\Application\Repository;

use App\Entity\PaymentCode;

/**
 * @extends RepositoryInterface<PaymentCode>
 */
interface PaymentCodeRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?PaymentCode;
}
