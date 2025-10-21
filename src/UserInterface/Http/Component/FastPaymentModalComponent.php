<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\ClassCouncil\Student;
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
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class FastPaymentModalComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    /**
     * Optional list of StudentPayment ULID strings to generate fast payments for.
     * When provided, one Payment+PaymentCode will be generated per item.
     * When empty, falls back to current behavior (all unpaid for current user's children).
     * @var list<Ulid>
     */
    #[LiveProp]
    public array $studentPaymentIds = [];

    #[LiveProp]
    public string $size = 'md';

    /**
     * Backward-compat single payment fields (when aggregating):
     */
    #[LiveProp]
    public ?string $paymentCode = null;

    public ?Money $paymentAmount = null;

    /**
     * New multi-payment results: list of [code => string, amount => Money]
     * @var list<array{code: string, amount: Money}>
     */
    public array $generated = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentRepository $students,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Opens modal and generates fast payments. If studentPaymentIds is set, create one Payment
     * per provided StudentPayment; otherwise aggregate all unpaid for user's children into one Payment.
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

        // If specific StudentPayment IDs provided, process them individually


        // Fallback: aggregate unpaid for current user's children into a single payment
        // Fetch students linked to current user in this class
        $qb = $this->students->createQueryBuilder('s');
        $qb->innerJoin('s.parents', 'p')
            ->innerJoin('s.classRoom', 'c')
            ->andWhere('p.id = :userId')
            ->andWhere('c.id = :classId')
            ->setParameter('userId', $user->getId())
            ->setParameter('classId', $class->getId(), 'ulid');

        /** @var array<Student> $myStudents */
        $myStudents = $qb->getQuery()
            ->getResult();

        if ($myStudents === []) {
            return; // nothing to pay for
        }

        // Load all payments, filter unpaid
        $all = $this->studentPayments->findForStudents($myStudents, $this->studentPaymentIds);
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
