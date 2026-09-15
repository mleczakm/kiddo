<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\SendWebPushNotification;
use App\Application\Repository\UserRepositoryInterface;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final readonly class InAppNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepositoryInterface $userRepository,
        private MessageBusInterface $bus,
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

        $userId = $user->getId();
        if ($userId !== null) {
            $this->bus->dispatch(new Envelope(new SendWebPushNotification($userId, $title, $body, $url))->with(
                new DispatchAfterCurrentBusStamp(),
            ));
        }

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
        bool $push = false,
    ): array {
        return $this->notifyUsers(
            $this->userRepository->findByRole('ROLE_ADMIN'),
            $title,
            $body,
            $url,
            $severity,
            $push,
        );
    }

    /**
     * Notify a batch of users at once — one Notification row per user, one
     * flush. Callers are responsible for any deduplication/exclusion (e.g.
     * dropping the acting user from the list) before calling this.
     *
     * Push defaults to off here: unlike notify() (a single recipient reacting
     * to their own action), this fans out to many people at once, and several
     * callers cover the whole admin list or the whole user base — sending a
     * real OS-level push to all of them on every call would turn this into a
     * mass-broadcast channel. Opt in per call site when that's actually wanted.
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
        bool $push = false,
    ): array {
        $notifications = [];
        foreach ($users as $user) {
            $notification = new Notification($user, $title, $body, $url, $severity);
            $this->em->persist($notification);
            $notifications[] = $notification;

            $userId = $user->getId();
            if ($push && $userId !== null) {
                $this->bus->dispatch(new Envelope(new SendWebPushNotification($userId, $title, $body, $url))->with(
                    new DispatchAfterCurrentBusStamp(),
                ));
            }
        }
        if ($notifications !== []) {
            $this->em->flush();
        }

        return $notifications;
    }
}
