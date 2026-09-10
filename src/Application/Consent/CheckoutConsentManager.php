<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Domain\Commerce\Order\BuyerType;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CheckoutConsentManager
{
    public function __construct(
        private ConsentRecorder $consentRecorder,
        private ConsentRequirements $consentRequirements,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {}

    public function isRequired(): bool
    {
        try {
            return $this->consentRequirements->isCheckoutAcceptanceRequired();
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to determine checkout consent requirements.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    /** @param list<WorkshopTermsEvidence> $workshopTerms */
    public function record(User $user, CustomerOrder $order, array $workshopTerms): void
    {
        if (!$this->isRequired()) {
            return;
        }

        try {
            $context = sprintf('order:%s', $order->getId());
            $termsText = $this->translator->trans('cart.checkout_terms_text');
            $grants = [
                new ConsentGrant(ConsentType::APP_TERMS, ConsentEvidence::currentDocument(
                    LegalDocumentType::APP_TERMS,
                    $termsText,
                    $context,
                )),
                new ConsentGrant(ConsentType::CLASSES_TERMS, ConsentEvidence::currentDocument(
                    LegalDocumentType::CLASSES_TERMS_GENERAL,
                    $termsText,
                    $context,
                )),
            ];

            foreach ($workshopTerms as $terms) {
                $grants[] = new ConsentGrant(ConsentType::CLASSES_TERMS, ConsentEvidence::externalDocument(
                    $terms->documentRef,
                    $terms->checksum,
                    $termsText,
                    $context,
                ));
            }

            if ($order->getBuyerType() === BuyerType::PRIVATE) {
                $grants[] = new ConsentGrant(ConsentType::WITHDRAWAL_INFO_ACK, ConsentEvidence::statement(
                    $this->translator->trans('cart.withdrawal_ack'),
                    $context,
                ));
            }

            $this->consentRecorder->recordMany($user, ConsentSource::CHECKOUT, ...$grants);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to prepare checkout consent evidence.', [
                'exception' => $exception,
                'user_id' => $user->getId(),
                'order_id' => (string) $order->getId(),
            ]);
        }
    }
}
