<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Entity\User;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\Clock;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class NewsletterSubscriptionManager
{
    public function __construct(
        private readonly BrevoNewsletterService $brevoNewsletterService,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Apply a newsletter state transition on the User entity and mirror it to Brevo.
     *
     * The User entity is mutated to reflect the desired state regardless of Brevo
     * connectivity — the DB is the source of truth for user intent, Brevo is a
     * downstream mirror. Failures are logged but never rethrown so the parent
     * flow (registration / profile edit) is not broken by a downstream outage.
     */
    public function applyTransition(User $user, bool $wasSubscribed, bool $desiredSubscribed): void
    {
        if ($wasSubscribed === $desiredSubscribed) {
            return;
        }

        if ($desiredSubscribed) {
            $user->setNewsletterSubscribed(true);
            $user->setNewsletterConsentDate(Clock::get()->now());

            try {
                $this->brevoNewsletterService->addOrUpdateContact($user->getEmail(), $user->getName());
            } catch (\RuntimeException|TransportExceptionInterface $exception) {
                $this->logger->warning('Failed to add contact to Brevo newsletter list', [
                    'email' => $user->getEmail(),
                    'exception' => $exception,
                ]);
            }

            return;
        }

        $user->setNewsletterSubscribed(false);
        $user->setNewsletterConsentDate(null);

        try {
            $this->brevoNewsletterService->removeContactFromList($user->getEmail());
        } catch (\RuntimeException|TransportExceptionInterface $exception) {
            $this->logger->warning('Failed to remove contact from Brevo newsletter list', [
                'email' => $user->getEmail(),
                'exception' => $exception,
            ]);
        }
    }
}
