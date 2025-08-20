<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Entity\Transfer;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class AssignPaymentModalComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp]
    public Transfer $transfer;

    #[LiveProp(writable: true)]
    public string $paymentSearch = '';

    #[LiveProp(writable: true)]
    public ?string $selectedPaymentId = null;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkflowInterface $paymentStateMachine
    ) {}

    /**
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->paymentRepository->findPendingWithSearch($this->paymentSearch);
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function selectPayment(#[LiveArg] string $paymentId): void
    {
        $this->selectedPaymentId = $paymentId;
    }

    #[LiveAction]
    public function confirmAssignment(): void
    {
        if (! $this->selectedPaymentId) {
            return;
        }

        $payment = $this->paymentRepository->find($this->selectedPaymentId);
        if (! $payment) {
            return;
        }

        $this->transfer->setPayment($payment);
        if ($this->paymentStateMachine->can($payment, 'pay')) {
            $this->paymentStateMachine->apply($payment, 'pay');
        }
        $this->entityManager->flush();

        $this->closeModal();
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
        $this->paymentSearch = '';
        $this->selectedPaymentId = null;
    }
}
