<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Accounting\PlatformBillingSummary;
use App\Application\Accounting\ReportingMonth;
use App\Application\Service\MoneyInputParser;
use App\Application\Service\PlatformBillingService;
use App\Application\Service\PlatformFeeCalculator;
use App\UserInterface\Form\PlatformBillingPaymentType;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
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

    /** Displayed month as `Y-m`; empty means the current month. */
    #[LiveProp]
    public string $month = '';

    public function __construct(
        private readonly PlatformBillingService $platformBillingService,
        private readonly PlatformFeeCalculator $feeCalculator,
    ) {}

    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \InvalidArgumentException
     */
    public function getSummary(): PlatformBillingSummary
    {
        $now = Clock::get()->now();
        $monthStart = ReportingMonth::start($this->month, $now);
        // The stored balance and its settlement actions only make sense for the running month.
        $isCurrentMonth = ReportingMonth::isCurrent($monthStart, $now);
        $currentDue = $isCurrentMonth
            ? $this->platformBillingService->getCurrentDue()
            : $this->feeCalculator->getFeeForMonth($monthStart);

        return new PlatformBillingSummary(
            isCurrentMonth: $isCurrentMonth,
            monthLabel: ReportingMonth::label($monthStart),
            currentDue: $currentDue->getAmount()->toFloat(),
            pastDue: $this->platformBillingService->getPastDue()->getAmount()->toFloat(),
            hasPastDue: $isCurrentMonth && $this->platformBillingService->hasPastDue(),
        );
    }

    /**
     * @throws \Brick\Math\Exception\MathException
     * @throws \Brick\Money\Exception\MoneyMismatchException
     * @throws \InvalidArgumentException
     */
    public function getCurrentDue(): float
    {
        return $this->getSummary()->currentDue;
    }

    #[LiveAction]
    public function setPastDueAsPaid(): void
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

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
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $form = $this->getForm();

        if (!$form->isSubmitted()) {
            return;
        }

        if (!$form->isValid()) {
            $this->errorMessage = 'Invalid form data';
            return;
        }

        /** @var array{amount: mixed} $data */
        $data = $form->getData();

        try {
            $normalized = MoneyInputParser::parse(is_string($data['amount']) ? $data['amount'] : null);
            $amount = $normalized !== null ? Money::of($normalized, 'PLN')->getAmount()->toFloat() : 0.0;

            if ($amount <= 0) {
                $this->errorMessage = 'Podaj poprawną kwotę, np. 150,00';
                return;
            }

            $this->platformBillingService->processPastDuePayment($amount);
            $this->successMessage = sprintf('Payment of %.2f PLN processed successfully', $amount);
            $this->errorMessage = null;
            $this->showModal = false;
        } catch (\InvalidArgumentException) {
            $this->errorMessage = 'Podaj poprawną kwotę, np. 150,00';
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to process payment: ' . $e->getMessage();
            $this->successMessage = null;
        }
    }

    #[\Override]
    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(PlatformBillingPaymentType::class);
    }
}
