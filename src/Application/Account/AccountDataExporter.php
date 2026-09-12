<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Repository\UserConsentRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Child;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;
use App\Entity\UserConsent;

/**
 * Builds the "download my data" payload (RODO art. 15/20). Plain nested arrays,
 * JSON-encoded by the caller. Scoped to what the account holder provided or
 * transacted - profile, children, bookings, payments and consent history.
 */
final readonly class AccountDataExporter
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private UserConsentRepositoryInterface $consentRepository,
        private ChildRepositoryInterface $childRepository,
    ) {}

    /**
     * @return array<string, mixed>
     * @throws \UnexpectedValueException
     */
    public function export(User $user): array
    {
        return [
            'generated_at' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
            'profile' => [
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone() !== null ? (string) $user->getPhone() : null,
                'roles' => $user->getRoles(),
                'created_at' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'newsletter_subscribed' => $user->isNewsletterSubscribed(),
                'newsletter_consent_date' => $user->getNewsletterConsentDate()?->format(\DateTimeInterface::ATOM),
            ],
            'children' => array_map(static fn(Child $child): array => [
                'name' => $child->getName(),
                'birthday' => $child->getBirthday()?->format('Y-m-d'),
            ], $this->childRepository->findByOwner($user)),
            'bookings' => array_map($this->exportBooking(...), $this->bookingRepository->findVisibleForUser($user)),
            'payments' => array_map(
                $this->exportPayment(...),
                $this->paymentRepository->findBy(['user' => $user], ['createdAt' => 'ASC']),
            ),
            'consents' => array_map($this->exportConsent(...), $this->consentRepository->findHistoryForUser($user)),
        ];
    }

    /** @return array<string, mixed> */
    private function exportBooking(Booking $booking): array
    {
        return [
            'id' => (string) $booking->getId(),
            'status' => $booking->getStatus(),
            'created_at' => $booking->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'lessons' => array_map(static fn(Lesson $lesson): array => [
                'title' => $lesson->getMetadata()->title,
                'schedule' => $lesson->schedule->format(\DateTimeInterface::ATOM),
            ], $booking->getLessons()->toArray()),
        ];
    }

    /** @return array<string, mixed> */
    private function exportPayment(Payment $payment): array
    {
        return [
            'id' => (string) $payment->getId(),
            'status' => $payment->getStatus(),
            'amount' => (string) $payment->getAmount()->getAmount(),
            'currency' => $payment->getAmount()->getCurrency()->getCurrencyCode(),
            'code' => $payment->getPaymentCodeSnapshot(),
            'created_at' => $payment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function exportConsent(UserConsent $consent): array
    {
        return [
            'type' => $consent->getType()->value,
            'source' => $consent->getSource()->value,
            'document_type' => $consent->getDocumentType()?->value,
            'document_version' => $consent->getDocumentVersion()?->getVersion(),
            'document_ref' => $consent->getDocumentRef(),
            'granted_at' => $consent->getGrantedAt()->format(\DateTimeInterface::ATOM),
            'revoked_at' => $consent->getRevokedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
