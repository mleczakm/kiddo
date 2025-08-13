<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\AddBooking;
use App\Application\Service\BookingFactory;
use App\Entity\PaymentFactory;
use App\Entity\Lesson;
use App\Entity\User;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LessonModal extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?Lesson $lesson = null;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public bool $termsAccepted = false;

    #[LiveProp(writable: true)]
    public ?string $selectedTicketType = null;

    #[LiveProp(writable: true)]
    public int $activeTabIndex = 0;

    #[LiveProp]
    public ?string $paymentStatus = null;

    #[LiveProp]
    public ?string $paymentCode = null;

    public ?Money $paymentAmount = null;

    #[LiveProp]
    public bool $termsOpened = false;

    #[LiveProp(writable: true)]
    public bool $showPaymentStep = false;

    #[LiveProp(writable: true)]
    public ?string $paymentMethod = null;

    #[LiveProp(writable: true)]
    public bool $paymentProcessing = false;

    #[LiveProp(writable: true)]
    public bool $paymentModal = false;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly BookingFactory $bookingFactory,
    ) {}

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;

        if ($this->lesson !== null && $this->selectedTicketType === null) {
            $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
            $this->selectedTicketType = $ticketOptions[$this->activeTabIndex]->type->value;
        }
    }

    #[LiveAction]
    public function openTerms(): void
    {
        $this->termsOpened = true;
    }

    #[LiveAction]
    public function closeTerms(): void
    {
        $this->termsOpened = false;
    }

    #[LiveAction]
    public function acceptTermsAndClose(): void
    {
        $this->termsAccepted = true;
        $this->closeTerms();
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
        $this->paymentModal = false;
    }

    #[LiveAction]
    public function openPaymentModal(): void
    {
        $this->paymentModal = true;
    }

    #[LiveAction]
    public function closePaymentModal(): void
    {
        $this->paymentModal = false;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] int $index, #[LiveArg('tickettype')] string $ticketType): void
    {
        $this->activeTabIndex = $index;
        $this->selectedTicketType = $ticketType;
    }

    #[LiveAction]
    public function proceedToPayment(#[LiveArg] string $paymentMethod): void
    {
        if (! $this->termsAccepted || ! $this->selectedTicketType) {
            $this->paymentStatus = 'error';
            return;
        }

        $this->paymentMethod = $paymentMethod;
        $this->showPaymentStep = true;
    }

    #[LiveAction]
    public function backToBooking(): void
    {
        $this->showPaymentStep = false;
        $this->paymentProcessing = false;
    }

    #[LiveAction]
    public function nextLesson(): void
    {
        if ($this->lesson === null) {
            return;
        }

        $nextLesson = $this->lesson->getSeries()?->getLessonsGt($this->lesson);
        if ($nextLesson) {
            $this->lesson = $nextLesson;
        }
    }

    public function hasNextLesson(): bool
    {
        if ($this->lesson === null) {
            return false;
        }

        return (bool) $this->lesson->getSeries()?->getLessonsGt($this->lesson);
    }

    #[LiveAction]
    public function previousLesson(): void
    {
        if ($this->lesson === null) {
            return;
        }

        $previousLesson = $this->lesson->getSeries()?->getLessonsLt($this->lesson);
        if ($previousLesson) {
            $this->lesson = $previousLesson;
        }
    }

    public function hasPreviousLesson(): bool
    {
        if ($this->lesson === null) {
            return false;
        }

        return (bool) $this->lesson->getSeries()?->getLessonsLt($this->lesson);
    }

    #[LiveAction]
    public function processPayment(): void
    {
        if (! $this->termsAccepted || ! $this->selectedTicketType) {
            $this->paymentStatus = 'error';
            return;
        }

        /** @var ?User $user */
        $user = $this->getUser();

        if ($this->lesson && $user) {
            $selected = $this->lesson->getMatchingTicketOption($this->selectedTicketType);

            $booking = $this->bookingFactory->createFrom($this->lesson, $selected, $user);
            $payment = new PaymentFactory()
                ->create($user, $selected->price);
            $booking->setPayment($payment);

            $this->bus->dispatch(new AddBooking($booking));

            $this->paymentCode = $payment->getPaymentCode()?->getCode();
            $this->paymentAmount = $selected->price;
            $this->paymentStatus = 'awaiting_payment';
            $this->paymentModal = false;

            return;
        }
        $this->paymentStatus = 'error';
    }

    public function getPaymentCode(): ?string
    {
        return $this->paymentCode;
    }
}
