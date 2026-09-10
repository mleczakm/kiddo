<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\Booking;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\Lesson;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BookingConsentManager
{
    public function __construct(
        private ConsentDispatcher $dispatcher,
        private ConsentRequirements $consentRequirements,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function isRequired(): bool
    {
        try {
            return $this->consentRequirements->isCheckoutAcceptanceRequired();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine booking consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function recordAfterBooking(User $user, Booking $booking, Lesson $lesson): void
    {
        if (!$this->isRequired()) {
            return;
        }

        try {
            $context = sprintf('booking:%s', $booking->getId());
            $acceptanceText = $this->translator->trans('booking.legal_acceptance_text');
            $grants = [
                new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                    LegalDocumentType::APP_TERMS,
                    $acceptanceText,
                    $context,
                )),
                new ConsentGrant(ConsentType::CLASSES_TERMS, ConsentEvidence::currentDocument(
                    LegalDocumentType::CLASSES_TERMS_GENERAL,
                    $acceptanceText,
                    $context,
                )),
            ];

            $terms = $lesson->getMetadata()->getTermsAttachment();
            if ($terms !== null) {
                $grants[] = new ConsentGrant(ConsentType::CLASSES_TERMS, ConsentEvidence::externalDocument(
                    sprintf('workshop_file:%s', $terms->getId()),
                    $terms->getFile()->getChecksum(),
                    $acceptanceText,
                    $context,
                ));
            }

            $grants[] = new ConsentGrant(ConsentType::WITHDRAWAL_INFO_ACK, ConsentEvidence::statement(
                $this->translator->trans('booking.withdrawal_ack'),
                $context,
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to build booking consent evidence.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'booking_id' => (string) $booking->getId(),
            ]);

            return;
        }

        // Deferred: recordAfterBooking runs inside PlaceSingleReservation's own
        // handler, so the consent write gets its own transaction.
        $this->dispatcher->recordDeferred($user, ConsentSource::BOOKING_MODAL, ...$grants);
    }
}
