<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class PaymentsListComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    ) {}

    #[LiveProp(writable: true, url: true)]
    public string $week;

    public function mount(): void
    {
        // Default to current date if not set
        $this->week ??= Clock::get()->now()->format('Y-m-d');
    }

    /**
     * @return list<Payment>
     */
    public function getPayments(): array
    {
        $start = new \DateTimeImmutable($this->week);
        $end = $start->modify('+7 days 23:59:59');

        // Order latest to oldest: prefer paidAt desc, then createdAt desc
        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.bookings', 'b')
            ->leftJoin('b.lessons', 'l')
            ->addSelect('u', 'b', 'l')
            ->andWhere('COALESCE(p.paidAt, p.createdAt) BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->addOrderBy('p.paidAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC');

        /** @var list<Payment> $result */
        $result = $qb->getQuery()
            ->getResult();
        return $result;
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->week);
    }

    public function getWeekEnd(): \DateTimeImmutable
    {
        return $this->getWeekStart()
            ->modify('+7 days');
    }
}
