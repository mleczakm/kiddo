<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\NewUser;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
readonly class NewUserHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(NewUser $command): void
    {
        $user = $command->user;
        $user->setConfirmedAt(Clock::get()->now());

        $this->sendUserConfirmation($user);
        $this->sendAdminInformation($user);
    }

    private function sendUserConfirmation(User $user): void
    {
        $subject = $this->translator->trans('user.notification.confirmation.subject', [], 'emails');
        $content = $this->translator->trans('user.notification.confirmation.message', [
            '%email%' => $user->getEmail(),
        ], 'emails');

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        $this->notifier->send($notification, new Recipient($user->getEmail()));
    }

    private function sendAdminInformation(User $user): void
    {
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        $subject = $this->translator->trans('user.notification.admin.subject', [
            '%email%' => $user->getEmail(),
        ], 'emails');
        $content = $this->translator->trans('user.notification.admin.content', [
            '%email%' => $user->getEmail(),
            '%name%' => $user->getName(),
            '%id%' => $user->getId(),
        ], 'emails');

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        foreach ($admins as $admin) {
            $this->notifier->send($notification, new Recipient($admin->getEmail()));
        }
    }
}
