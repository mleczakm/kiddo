<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\RecordConsents;
use App\Application\Command\RequestAccountClosure;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Applies a self-service account closure: a reversible pause, or the start of
 * the deletion grace period. Records an ACCOUNT_DELETION consent row for the
 * permanent case and e-mails the user how to undo it (just log back in).
 */
final readonly class RequestAccountClosureHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private MessageBusInterface $commandBus,
        private NotificationSenderInterface $notificationSender,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function __invoke(RequestAccountClosure $command): void
    {
        $user = $this->userRepository->find($command->userId);
        if ($user === null || $user->getLifecycle()->isAnonymized()) {
            return;
        }

        $now = Clock::get()->now();

        if ($command->permanent) {
            $user->getLifecycle()->requestDeletion($now);
            $this->commandBus->dispatch(new RecordConsents($user, ConsentSource::ACCOUNT_SETTINGS, [
                new ConsentGrant(
                    ConsentType::ACCOUNT_DELETION,
                    ConsentEvidence::statement($this->translator->trans('account.close.delete_statement')),
                ),
            ]));
            $key = 'account.close.email.delete';
        } else {
            $user->getLifecycle()->deactivate($now);
            $key = 'account.close.email.pause';
        }

        $this->notificationSender->send(
            $user->getEmail(),
            $this->translator->trans($key . '.subject', [], 'emails'),
            $this->translator->trans(
                $key . '.body',
                ['grace' => AnonymizeExpiredAccountsHandler::GRACE_DAYS],
                'emails',
            ),
        );
    }
}
