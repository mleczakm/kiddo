<?php

declare(strict_types=1);

namespace App\Application\Service\Payment;

use App\Application\Repository\PaymentCodeRepositoryInterface;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single place payment codes get generated (Stage 6 of the commerce rollout
 * plan) - previously duplicated between PaymentCode::generateCode() and
 * PaymentFactory::generateCode() with two different alphabets. Still 4
 * characters, so the user-visible flow (a short code to write in a bank
 * transfer title) does not change.
 */
final readonly class PaymentCodeGenerator
{
    private const int MAX_ATTEMPTS = 5;

    public function __construct(
        private PaymentCodeRepositoryInterface $paymentCodeRepository,
        private EntityManagerInterface $em,
    ) {}

    public function generate(): string
    {
        $max = strlen(PaymentCode::CHARS) - 1;
        $code = '';

        for ($i = 0; $i < PaymentCode::CODE_LENGTH; $i++) {
            $code .= PaymentCode::CHARS[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * For codes shown to the user before anything is persisted (e.g. a fast
     * reservation's optimistic UI preview): checks each candidate against
     * existing codes so what's displayed is very unlikely to collide once
     * actually persisted downstream.
     */
    public function generateAvailable(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->generate();
            if ($this->paymentCodeRepository->findOneByCode($candidate) === null) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Could not find an available payment code after ' . self::MAX_ATTEMPTS . ' attempts.',
        );
    }

    /**
     * For codes generated and persisted in the same step, with nothing shown
     * to the user beforehand. Checks availability before constructing the
     * entity rather than catching a unique-constraint violation at flush time
     * - a caught flush exception leaves the EntityManager closed, so retrying
     * on the same instance would need savepoint/reopen machinery that isn't
     * worth it at this scale (a residual, check-then-insert race is fine).
     */
    public function createFor(Payment $payment): PaymentCode
    {
        $paymentCode = new PaymentCode($payment, $this->generateAvailable());
        $this->em->persist($paymentCode);
        $this->em->flush();

        return $paymentCode;
    }
}
