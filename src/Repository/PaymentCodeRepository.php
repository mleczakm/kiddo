<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentCode>
 */
class PaymentCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentCode::class);
    }

    public function findOneByCode(string $code): ?PaymentCode
    {
        return $this->findOneBy([
            'code' => $code,
        ]);
    }
}
