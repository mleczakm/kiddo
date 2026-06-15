<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Form\PlatformBillingPaymentType;
use App\Application\Service\PlatformBillingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class PlatformBillingComponent extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public bool $showModal = false;

    #[LiveProp]
    public ?string $successMessage = null;

    #[LiveProp]
    public ?string $errorMessage = null;

    public function __construct(
        private readonly PlatformBillingService $platformBillingService
    ) {}

    public function getCurrentDue(): float
    {
        return $this->platformBillingService->getCurrentDue()
            ->getAmount()
            ->toFloat();
    }

    public function getPastDue(): float
    {
        return $this->platformBillingService->getPastDue()
            ->getAmount()
            ->toFloat();
    }

    public function hasPastDue(): bool
    {
        return $this->platformBillingService->hasPastDue();
    }

    #[LiveAction]
    public function setPastDueAsPaid(): void
    {
        try {
            $this->platformBillingService->setPastDueAsPaid();
            $this->successMessage = 'Past due has been marked as paid';
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to mark past due as paid: ' . $e->getMessage();
            $this->successMessage = null;
        }
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->showModal = true;
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    #[LiveAction]
    public function processPayment(): void
    {
        $form = $this->getForm();

        if (! $form->isSubmitted()) {
            return;
        }

        if (! $form->isValid()) {
            $this->errorMessage = 'Invalid form data';
            return;
        }

        /** @var array{amount: mixed} $data */
        $data = $form->getData();
        $amount = is_numeric($data['amount']) ? (float) $data['amount'] : 0.0;

        try {
            $this->platformBillingService->processPastDuePayment($amount);
            $this->successMessage = sprintf('Payment of %.2f PLN processed successfully', $amount);
            $this->errorMessage = null;
            $this->showModal = false;
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to process payment: ' . $e->getMessage();
            $this->successMessage = null;
        }
    }

    /**
     * @phpstan-ignore missingType.generics
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(PlatformBillingPaymentType::class);
    }
}
