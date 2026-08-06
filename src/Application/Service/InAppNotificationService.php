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
        $notifications = [];
        foreach ($this->userRepository->findByRole('ROLE_ADMIN') as $admin) {
            $notification = new Notification($admin, $title, $body, $url, $severity);
            $this->em->persist($notification);
            $notifications[] = $notification;
        }
        if ($notifications !== []) {
            $this->em->flush();
        }

        return $notifications;
    }
}
