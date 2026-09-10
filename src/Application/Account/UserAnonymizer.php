<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Entity\ActivityLog;
use App\Entity\Booking;
use App\Entity\Child;
use App\Entity\Notification;
use App\Entity\RefundRequest;
use App\Entity\User;
use App\Entity\UserConsent;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Irreversibly strips a user's personal data while keeping the row and its
 * financial records (Payment/Transfer/Order) intact for statutory retention.
 * Idempotent: a user already marked anonymised is skipped.
 */
final readonly class UserAnonymizer
{
    private const string CHILD_PLACEHOLDER = 'Uczestnik';

    private const string LOG_PLACEHOLDER = '[dane usunięte]';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BrevoNewsletterService $brevoNewsletterService,
        private LoggerInterface $logger,
    ) {}

    /** @throws \UnexpectedValueException */
    public function anonymize(User $user): void
    {
        if ($user->getLifecycle()->isAnonymized()) {
            return;
        }

        $originalEmail = $user->getEmail();
        $now = Clock::get()->now();

        $this->entityManager
            ->createQuery(
                'UPDATE ' . Child::class . ' c SET c.name = :placeholder, c.birthday = NULL WHERE c.owner = :user',
            )
            ->setParameter('placeholder', self::CHILD_PLACEHOLDER)
            ->setParameter('user', $user)
            ->execute();

        $this->entityManager
            ->createQuery('UPDATE ' . Booking::class . ' b SET b.notes = NULL WHERE b.user = :user')
            ->setParameter('user', $user)
            ->execute();

        $this->entityManager
            ->createQuery(
                'UPDATE ' . RefundRequest::class . ' r SET r.requestMessage = NULL WHERE r.requestedBy = :user',
            )
            ->setParameter('user', $user)
            ->execute();

        $this->entityManager
            ->createQuery(
                'UPDATE '
                . UserConsent::class
                . ' c SET c.revokedAt = :now WHERE c.user = :user AND c.revokedAt IS NULL',
            )
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->execute();

        $this->entityManager
            ->createQuery('DELETE ' . Notification::class . ' n WHERE n.user = :user')
            ->setParameter('user', $user)
            ->execute();

        foreach ($this->entityManager->getRepository(ActivityLog::class)->findBy(['subject' => $user]) as $log) {
            $log->redactSubject(self::LOG_PLACEHOLDER);
        }

        $user->anonymize($now);
        $this->entityManager->flush();

        try {
            $this->brevoNewsletterService->removeContactFromList($originalEmail);
        } catch (\RuntimeException|TransportExceptionInterface $exception) {
            $this->logger->warning('Failed to remove anonymised contact from Brevo.', ['exception' => $exception]);
        }

        $this->logger->info('User anonymised.', ['user_id' => $user->getId()]);
    }
}
