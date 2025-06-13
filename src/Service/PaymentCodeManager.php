<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Repository\PaymentCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class PaymentCodeManager
{
    private const int MAX_ATTEMPTS = 10;

    private const int LOCK_TTL = 30; // 30 seconds lock TTL

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentCodeRepository $paymentCodeRepository,
        private readonly LockFactory $lockFactory,
    ) {}

    public function createPaymentCode(Payment $payment): PaymentCode
    {
        $attempt = 0;
        $lock = null;

        do {
            $attempt++;

            // Generate a payment code
            $paymentCode = new PaymentCode($payment);
            $code = $paymentCode->getCode();

            // Try to acquire a lock for this code
            $lock = $this->acquireCodeLock($code);

            if (! $lock) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new \RuntimeException(
                        'Could not acquire lock for payment code generation after multiple attempts'
                    );
                }
                continue;
            }

            // Check if code is already in use
            $existingCode = $this->paymentCodeRepository->findOneByCode($code);

            if (! $existingCode) {
                // Save the new payment code
                $this->entityManager->persist($paymentCode);
                $this->entityManager->flush();

                $lock->release();
                return $paymentCode;
            }

            $lock->release();

        } while ($attempt < self::MAX_ATTEMPTS);

        throw new \RuntimeException('Failed to generate a unique payment code after multiple attempts');
    }

    private function acquireCodeLock(string $code): ?LockInterface
    {
        $lock = $this->lockFactory->createLock('payment_code_' . $code, self::LOCK_TTL);

        if ($lock->acquire()) {
            return $lock;
        }

        return null;
    }
}
