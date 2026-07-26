<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\AddBooking;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\PaymentFactory;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ChildRepository;
use App\Repository\PaymentCodeRepository;
use App\Repository\PaymentRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LessonModal extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public ?Lesson $lesson = null;

    #[LiveProp]
    public string $closeUrl = '/';

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

    /**
     * @var numeric-string|null
     */
    #[LiveProp]
    public ?string $paymentAmountMinor = null;

    #[LiveProp]
    public ?string $paymentCurrency = null;

    #[LiveProp(writable: true)]
    public ?string $resumedBookingId = null;

    #[LiveProp(writable: true)]
    public ?string $watchedPaymentId = null;

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

    #[LiveProp(writable: true)]
    public ?string $selectedChildId = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ChildRepository $childRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentCodeRepository $paymentCodeRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;

        if ($this->lesson !== null && $this->selectedTicketType === null) {
            $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
            $this->selectedTicketType = $ticketOptions[$this->activeTabIndex]->type->value;
        }

        $this->syncBrowserUrl();
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
        $this->paymentCode = null;
        $this->paymentAmountMinor = null;
        $this->paymentCurrency = null;
        $this->paymentStatus = null;
        $this->resumedBookingId = null;
        $this->watchedPaymentId = null;
        $this->syncBrowserUrl();
    }

    private function syncBrowserUrl(): void
    {
        $this->dispatchBrowserEvent('workshop:url-change', [
            'url' => $this->modalOpened ? $this->workshopUrl() : $this->closeUrl,
        ]);
    }

    private function workshopUrl(): string
    {
        $metadata = $this->lesson?->getMetadata();
        if ($metadata === null || $metadata->slug === null || $metadata->slug === '') {
            return $this->closeUrl;
        }

        return $this->urlGenerator->generate('workshop_by_slug', [
            'slug' => $metadata->slug,
            'date' => $metadata->schedule->format('Y-m-d'),
            'hour' => $metadata->schedule->format('H:i'),
        ]);
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

    /**
     * @return array<array{id: non-empty-string, name: string, birthday: non-falsy-string|null}>
     */
    public function getChildren(): array
    {
        /** @var ?User $user */
        $user = $this->getUser();
        if (! $user) {
            return [];
        }

        return array_map(
            static fn($c) => [
                'id' => (string) $c->getId(),
                'name' => $c->getName(),
                'birthday' => $c->getBirthday()?->format('Y-m-d'),
            ],
            $this->childRepository->findByOwner($user)
        );
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
            $userId = $user->getId();
            if ($userId === null) {
                $this->paymentStatus = 'error';
                return;
            }

            $selected = $this->lesson->getMatchingTicketOption($this->selectedTicketType);

            $paymentCode = new PaymentFactory()
                ->generateCode();

            $this->bus->dispatch(new AddBooking(
                userId: $userId,
                lessonId: (string) $this->lesson->getId(),
                ticketType: $this->selectedTicketType,
                childId: $this->selectedChildId,
                paymentCode: $paymentCode,
            ));

            $this->paymentCode = $paymentCode;
            $this->setPaymentAmount($selected->price);
            $this->paymentStatus = 'awaiting_payment';
            $this->paymentModal = false;
            $this->watchedPaymentId = $this->resolvePaymentIdByCode($paymentCode);

            return;
        }
        $this->paymentStatus = 'error';
    }

    private function setPaymentAmount(Money $amount): void
    {
        $this->paymentAmountMinor = (string) $amount->getMinorAmount()
            ->toInt();
        $this->paymentCurrency = $amount->getCurrency()
            ->getCurrencyCode();
    }

    public function getPaymentAmount(): ?Money
    {
        if ($this->paymentAmountMinor === null || $this->paymentCurrency === null) {
            return null;
        }

        return Money::ofMinor((int) $this->paymentAmountMinor, $this->paymentCurrency);
    }

    private function resolvePaymentIdByCode(string $code): ?string
    {
        $paymentCode = $this->paymentCodeRepository->findOneByCode($code);

        return $paymentCode !== null ? (string) $paymentCode->getPayment()
            ->getId() : null;
    }

    public function getPaymentCode(): ?string
    {
        return $this->paymentCode;
    }

    /**
     * @return array<Booking>
     */
    public function getExistingBookings(): array
    {
        if ($this->lesson === null) {
            return [];
        }

        /** @var ?User $user */
        $user = $this->getUser();
        if (! $user instanceof User) {
            return [];
        }

        return $this->bookingRepository->findForUserAndLesson($user, $this->lesson);
    }

    #[LiveAction]
    public function resumePayment(#[LiveArg] string $bookingId): void
    {
        if ($this->lesson === null) {
            return;
        }

        /** @var ?User $user */
        $user = $this->getUser();
        if (! $user instanceof User) {
            return;
        }

        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        } catch (\Throwable) {
            return;
        }

        if (! $booking instanceof Booking) {
            return;
        }

        $bookingUserId = $booking->getUser()
            ->getId();
        $currentUserId = $user->getId();
        if ($bookingUserId === null || $currentUserId === null || $bookingUserId !== $currentUserId) {
            return;
        }

        $lessonId = $this->lesson->getId();
        $belongsToLesson = false;
        foreach ($booking->getLessons() as $bookedLesson) {
            if ($bookedLesson->getId()->equals($lessonId)) {
                $belongsToLesson = true;
                break;
            }
        }
        if (! $belongsToLesson) {
            return;
        }

        $payment = $booking->getPayment();
        if (! $payment instanceof Payment) {
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_PAID) {
            return;
        }

        $paymentCode = $payment->getPaymentCode();
        if ($paymentCode === null) {
            $paymentCode = new PaymentCode($payment);
            $this->entityManager->persist($paymentCode);
            $this->entityManager->flush();
        }

        $this->resumedBookingId = $bookingId;
        $this->paymentCode = $paymentCode->getCode();
        $this->setPaymentAmount($payment->getAmount());
        $this->paymentStatus = 'awaiting_payment';
        $this->paymentModal = false;
        $this->watchedPaymentId = (string) $payment->getId();
    }

    #[LiveAction]
    public function refreshPaymentStatus(): void
    {
        if ($this->paymentStatus !== 'awaiting_payment') {
            return;
        }

        $payment = $this->resolveWatchedPayment();
        if ($payment === null) {
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_PAID) {
            $this->paymentStatus = 'paid';
            $this->paymentCode = null;
            $this->resumedBookingId = null;
            $this->watchedPaymentId = null;
        }
    }

    private function resolveWatchedPayment(): ?Payment
    {
        if ($this->watchedPaymentId !== null && $this->watchedPaymentId !== '') {
            try {
                $payment = $this->paymentRepository->find(Ulid::fromString($this->watchedPaymentId));
            } catch (\Throwable) {
                $payment = null;
            }
            if ($payment instanceof Payment) {
                return $payment;
            }
        }

        if ($this->paymentCode === null) {
            return null;
        }

        $paymentCode = $this->paymentCodeRepository->findOneByCode($this->paymentCode);
        if ($paymentCode === null) {
            return null;
        }

        $this->watchedPaymentId = (string) $paymentCode->getPayment()
            ->getId();

        return $paymentCode->getPayment();
    }
}
