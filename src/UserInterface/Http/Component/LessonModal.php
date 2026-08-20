<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\AddBooking;
use App\Application\Service\Payment\PaymentCodeGenerator;
use App\Application\Service\Pricing\PriceQuoter;
use App\Application\UseCase\PriceQuoteMismatchException;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\ChildRepository;
use App\Infrastructure\Doctrine\Repository\PaymentCodeRepository;
use App\Infrastructure\Doctrine\Repository\PaymentRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
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

    #[LiveProp]
    public ?string $week = null;

    #[LiveProp]
    public bool $hideOpenButton = false;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public bool $termsAccepted = false;

    #[LiveProp(writable: true)]
    public ?string $selectedTicketType = null;

    /**
     * The PriceQuote last displayed for $selectedTicketType (see
     * PriceQuoter). Sent back to the server on confirm so PlaceSingleReservation
     * can detect a stale price (Stage 8 of the commerce rollout plan).
     */
    #[LiveProp]
    public ?string $expectedQuoteHash = null;

    /**
     * @var numeric-string|null
     */
    #[LiveProp]
    public ?string $quotedPriceMinor = null;

    #[LiveProp]
    public ?string $quotedPriceCurrency = null;

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

    #[LiveProp(writable: true)]
    public string $phone = '';

    #[LiveProp]
    public ?string $phoneError = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ChildRepository $childRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentCodeRepository $paymentCodeRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentCodeGenerator $paymentCodeGenerator,
        private readonly PriceQuoter $priceQuoter,
    ) {}

    public function mount(): void
    {
        $this->selectDefaultTicketType();
        $this->refreshQuote();
    }

    /**
     * Recomputes the quote for the currently selected ticket type and stores
     * it (price + hash) so it survives to the next request unchanged, even
     * though everything else on this component is recalculated per-render -
     * $expectedQuoteHash must reflect what was actually shown to the user,
     * not whatever the price happens to be *right now* when confirming.
     */
    private function refreshQuote(): void
    {
        if ($this->lesson === null || $this->selectedTicketType === null) {
            $this->expectedQuoteHash = null;
            $this->quotedPriceMinor = null;
            $this->quotedPriceCurrency = null;
            return;
        }

        $ticketOption = $this->lesson->getMatchingTicketOption($this->selectedTicketType);
        /** @var ?User $user */
        $user = $this->getUser();

        $quote = $this->priceQuoter->quote(
            $user?->getId(),
            $this->lesson,
            $this->selectedTicketType,
            $ticketOption->price,
        );

        $this->expectedQuoteHash = $quote->quoteHash;
        $this->quotedPriceMinor = (string) $quote->finalPriceMinor;
        $this->quotedPriceCurrency = $quote->currency;
    }

    public function getQuotedPrice(): ?Money
    {
        if ($this->quotedPriceMinor === null || $this->quotedPriceCurrency === null) {
            return null;
        }

        return Money::ofMinor((int) $this->quotedPriceMinor, $this->quotedPriceCurrency);
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
        $this->phoneError = null;

        $this->selectDefaultTicketType();
        $this->refreshQuote();

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

    /**
     * The workshop's own terms-of-use attachment, when the admin has set
     * one; otherwise the shared default that used to be the only option.
     *
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function getTermsUrl(): string
    {
        $termsAttachment = $this->lesson?->getMetadata()->getTermsAttachment();
        if ($termsAttachment === null) {
            return '/docs/Regulamin.pdf';
        }

        return $this->urlGenerator->generate('stored_file', [
            'id' => (string) $termsAttachment->getFile()->getId(),
            'safeName' => $termsAttachment->getFile()->getOriginalName(),
        ]);
    }

    private function workshopUrl(): string
    {
        $lesson = $this->lesson;
        $metadata = $lesson?->getMetadata();
        if ($lesson === null || $metadata === null || $metadata->slug === null || $metadata->slug === '') {
            return $this->closeUrl;
        }

        return $this->urlGenerator->generate('workshop_by_slug', [
            'slug' => $metadata->slug,
            'date' => $lesson->schedule->format('Y-m-d'),
            'hour' => $lesson->schedule->format('H:i'),
            'week' => $this->week,
        ]);
    }

    #[LiveAction]
    public function openPaymentModal(): void
    {
        $this->paymentModal = true;
        $this->phoneError = null;
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
        $this->refreshQuote();
    }

    #[LiveAction]
    public function proceedToPayment(#[LiveArg] string $paymentMethod): void
    {
        $selectedTicketType = $this->selectedTicketType;
        if (!$this->termsAccepted || $selectedTicketType === null || $selectedTicketType === '') {
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
     * @return array<array{id: non-empty-string, name: string, birthday: non-empty-string|null}>
     */
    public function getChildren(): array
    {
        /** @var ?User $user */
        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        return array_map(static fn($c) => [
            'id' => (string) $c->getId(),
            'name' => $c->getName(),
            'birthday' => $c->getBirthday()?->format('Y-m-d'),
        ], $this->childRepository->findByOwner($user));
    }

    #[LiveAction]
    public function processPayment(): void
    {
        $this->selectDefaultTicketType();
        if ($this->expectedQuoteHash === null) {
            // Self-heals an entry path where mount()/openModal()/selectTab() never got a
            // chance to compute one (e.g. the modal opened pre-expanded via a direct URL) -
            // better to charge the current price without staleness protection for this one
            // request than to silently skip the check for every booking that takes this path.
            $this->refreshQuote();
        }

        $selectedTicketType = $this->selectedTicketType;
        if (!$this->termsAccepted || $selectedTicketType === null || $selectedTicketType === '') {
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

            if (!$this->ensurePhoneOnAccount($user)) {
                return;
            }

            $paymentCode = $this->paymentCodeGenerator->generateAvailable();

            try {
                $this->bus->dispatch(new AddBooking(
                    userId: $userId,
                    lessonId: (string) $this->lesson->getId(),
                    ticketType: $selectedTicketType,
                    childId: $this->selectedChildId,
                    paymentCode: $paymentCode,
                    expectedQuoteHash: $this->expectedQuoteHash,
                ));
            } catch (HandlerFailedException|PriceQuoteMismatchException $e) {
                if (!$this->handlePriceQuoteMismatch($e)) {
                    throw $e;
                }

                return;
            }

            $this->paymentCode = $paymentCode;
            // The price actually charged is exactly $this->getQuotedPrice() - the
            // dispatch above only succeeds when the server confirms that quote is
            // still current (or dynamic_pricing is off, in which case it's unchanged
            // from the ticket price anyway).
            $this->setPaymentAmount(
                $this->getQuotedPrice() ?? $this->lesson->getMatchingTicketOption($selectedTicketType)->price,
            );
            $this->paymentStatus = 'awaiting_payment';
            $this->paymentModal = false;
            $this->watchedPaymentId = $this->resolvePaymentIdByCode($paymentCode);

            return;
        }
        $this->paymentStatus = 'error';
    }

    /**
     * @return bool True if $e was a stale-quote failure and has been handled
     *   (the caller should stop processing); false if it must be rethrown.
     */
    private function handlePriceQuoteMismatch(\Throwable $e): bool
    {
        $mismatch = $e instanceof PriceQuoteMismatchException ? $e : null;
        if ($mismatch === null && $e instanceof HandlerFailedException) {
            // getWrappedExceptions() is keyed by handler name, not a sequential index.
            $firstWrapped = current($e->getWrappedExceptions());
            $mismatch = $firstWrapped instanceof PriceQuoteMismatchException ? $firstWrapped : null;
        }

        if ($mismatch === null) {
            return false;
        }

        $quote = $mismatch->currentQuote;
        $this->expectedQuoteHash = $quote->quoteHash;
        $this->quotedPriceMinor = (string) $quote->finalPriceMinor;
        $this->quotedPriceCurrency = $quote->currency;
        $this->paymentStatus = 'price_changed';

        return true;
    }

    private function selectDefaultTicketType(): void
    {
        if ($this->lesson === null || $this->selectedTicketType !== null) {
            return;
        }

        $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
        $selected = $ticketOptions[$this->activeTabIndex] ?? $ticketOptions[0] ?? null;
        $this->selectedTicketType = $selected?->type->value;
    }

    public function needsPhone(): bool
    {
        /** @var ?User $user */
        $user = $this->getUser();

        return $user instanceof User && $user->getPhone() === null;
    }

    private function ensurePhoneOnAccount(User $user): bool
    {
        $this->phoneError = null;

        if ($user->getPhone() !== null) {
            return true;
        }

        $raw = trim($this->phone);
        if ($raw === '') {
            $this->phoneError = 'booking.phone.required';
            return false;
        }

        try {
            $parsed = PhoneNumberUtil::getInstance()->parse($raw, 'PL');
            if (!PhoneNumberUtil::getInstance()->isValidNumber($parsed)) {
                $this->phoneError = 'booking.phone.invalid';
                return false;
            }
        } catch (NumberParseException) {
            $this->phoneError = 'booking.phone.invalid';
            return false;
        }

        $user->setPhone($parsed);
        $this->entityManager->flush();

        return true;
    }

    private function setPaymentAmount(Money $amount): void
    {
        $this->paymentAmountMinor = (string) $amount->getMinorAmount()->toInt();
        $this->paymentCurrency = $amount->getCurrency()->getCurrencyCode();
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

        return $paymentCode !== null ? (string) $paymentCode->getPayment()->getId() : null;
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
        if (!$user instanceof User) {
            return [];
        }

        return $this->bookingRepository->findForUserAndLesson($user, $this->lesson);
    }

    #[LiveAction]
    #[LiveListener('resumePayment')]
    public function resumePayment(#[LiveArg] string $bookingId): void
    {
        if ($this->lesson === null) {
            return;
        }

        /** @var ?User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return;
        }

        try {
            $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        } catch (\Throwable) {
            return;
        }

        if (!$booking instanceof Booking) {
            return;
        }

        $bookingUserId = $booking->getUser()->getId();
        $currentUserId = $user->getId();
        if ($bookingUserId === null || $currentUserId === null || $bookingUserId !== $currentUserId) {
            return;
        }

        $lessonId = $this->lesson->getId();
        $belongsToLesson = false;
        foreach ($booking->getLessons() as $bookedLesson) {
            if (!$bookedLesson->getId()->equals($lessonId)) {
                continue;
            }

            $belongsToLesson = true;
            break;
        }
        if (!$belongsToLesson) {
            return;
        }

        $payment = $booking->getPayment();
        if (!$payment instanceof Payment) {
            return;
        }

        if ($payment->getStatus() === Payment::STATUS_PAID) {
            return;
        }

        $paymentCode = $payment->getPaymentCode();
        if ($paymentCode === null) {
            $paymentCode = $this->paymentCodeGenerator->createFor($payment);
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

        $this->watchedPaymentId = (string) $paymentCode->getPayment()->getId();

        return $paymentCode->getPayment();
    }
}
