<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\UseCase\AssignPaymentToTransfer;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
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

    /**
     * Nullable on purpose: the modal is rendered once per pending transfer,
     * and the referenced row can be gone by the time a later action re-hydrates
     * this component (rejected elsewhere, assigned in another tab). A missing
     * transfer must degrade to a no-op, not a hydration TypeError.
     */
    #[LiveProp]
    public ?Transfer $transfer = null;

    #[LiveProp(writable: true)]
    public string $paymentSearch = '';

    #[LiveProp(writable: true)]
    public ?string $selectedPaymentId = null;

    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly AssignPaymentToTransfer $assignPaymentToTransfer,
        private readonly LoggerInterface $logger,
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
        if ($this->transfer === null) {
            return;
        }

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
        $transferId = $this->transfer?->getId();
        if ($transferId === null || !$this->selectedPaymentId) {
            $this->closeModal();

            return;
        }

        try {
            ($this->assignPaymentToTransfer)($transferId, Ulid::fromString($this->selectedPaymentId));
        } catch (\Throwable $exception) {
            // The transfer or payment moved on between opening the modal and
            // confirming (rejected, already assigned, no longer pending). The
            // refreshed list reflects the new state; just leave a trace.
            $this->logger->warning('Manual payment assignment was rejected', [
                'transferId' => $transferId,
                'paymentId' => $this->selectedPaymentId,
                'reason' => $exception->getMessage(),
            ]);
        }

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
