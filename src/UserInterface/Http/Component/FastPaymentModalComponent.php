<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\User;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\StudentRepository;
use App\Tenant\TenantContext;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class FastPaymentModalComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentRepository $students,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly EntityManagerInterface $em,
    ) {}

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp]
    public ?string $paymentCode = null;

    public ?Money $paymentAmount = null;

    /**
     * Opens modal and generates a single Payment for the sum of all unpaid required payments
     * for the current user's children in the current tenant's class.
     */
    #[LiveAction]
    public function openModal(): void
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            return; // not logged in
        }

        $tenant = $this->tenantContext->getTenant();
        $class = $tenant ? $this->classRooms->findOneBy([
            'tenant' => $tenant,
        ]) : null;
        if (! $class) {
            return;
        }

        // Fetch students linked to current user in this class
        $qb = $this->students->createQueryBuilder('s');
        $qb->innerJoin('s.parents', 'p')
            ->innerJoin('s.classRoom', 'c')
            ->andWhere('p.id = :userId')
            ->andWhere('c.id = :classId')
            ->setParameter('userId', $user->getId())
            ->setParameter('classId', $class->getId(), 'ulid');
        $myStudents = $qb->getQuery()
            ->getResult();

        if ($myStudents === []) {
            return; // nothing to pay for
        }

        // Load all payments, filter unpaid
        $all = $this->studentPayments->findForStudents($myStudents);
        $unpaid = array_filter($all, fn($sp) => $sp->getStatus() !== StudentPayment::STATUS_PAID);

        if ($unpaid === []) {
            return; // nothing to pay
        }

        // Sum amounts
        $sum = Money::of(0, 'PLN');
        foreach ($unpaid as $sp) {
            $sum = $sum->plus($sp->getAmount());
        }

        // Create Payment + PaymentCode
        $payment = new Payment($user, $sum);
        $this->em->persist($payment);
        $code = new PaymentCode($payment);
        $this->em->persist($code);

        // Link all unpaid student payments to this payment so assignment will mark them paid later
        foreach ($unpaid as $sp) {
            $sp->setPayment($payment);
        }

        $this->em->flush();

        $this->paymentCode = $code->getCode();
        $this->paymentAmount = $sum;
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }
}
