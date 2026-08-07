<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InAppNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {}

    public function notify(
        User $user,
        string $title,
        ?string $body = null,
        ?string $url = null,
        NotificationSeverity $severity = NotificationSeverity::Info,
    ): Notification {
        $notification = new Notification($user, $title, $body, $url, $severity);
        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /**
     * @return list<Notification>
     */
    public function notifyAdmins(
        string $title,
        ?string $body = null,
        ?string $url = null,
        NotificationSeverity $severity = NotificationSeverity::Info,
    ): array {
        return $this->notifyUsers($this->userRepository->findByRole('ROLE_ADMIN'), $title, $body, $url, $severity);
    }

    /**
     * Notify a batch of users at once — one Notification row per user, one
     * flush. Callers are responsible for any deduplication/exclusion (e.g.
     * dropping the acting user from the list) before calling this.
     *
     * @param iterable<User> $users
     * @return list<Notification>
     */
    public function notifyUsers(
        iterable $users,
        string $title,
        ?string $body = null,
        ?string $url = null,
        NotificationSeverity $severity = NotificationSeverity::Info,
    ): array {
        $notifications = [];
        foreach ($users as $user) {
            $notification = new Notification($user, $title, $body, $url, $severity);
            $this->em->persist($notification);
            $notifications[] = $notification;
        }
        if ($notifications !== []) {
            $this->em->flush();
        }

        return $notifications;
    }
}
