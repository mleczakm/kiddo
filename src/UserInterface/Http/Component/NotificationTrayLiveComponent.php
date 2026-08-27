<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Notification;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class NotificationTrayLiveComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    /**
     * @var list<array{email: string,name: string}>
     */
    #[LiveProp(writable: false)]
    public array $suggestions = [];

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
    ) {}

    /**
     * @return Notification[]
     */
    public function getItems(): array
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->notifications->findRecentForUser($user, 30);
    }

    public function getUnreadCount(): int
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->notifications->countUnreadForUser($user);
    }

    #[LiveAction]
    public function open(#[LiveArg] string $id): ?RedirectResponse
    {
        $notification = $this->findOwnedNotification($id);
        if ($notification === null) {
            return null;
        }

        $notification->markRead();
        $this->em->flush();

        $url = $notification->getUrl();
        if ($url !== null && $url !== '') {
            return $this->redirect($url);
        }

        return null;
    }

    #[LiveAction]
    public function delete(#[LiveArg] string $id): void
    {
        $notification = $this->findOwnedNotification($id);
        if ($notification === null) {
            return;
        }

        $notification->softDelete();
        $this->em->flush();
    }

    /**
     * @throws \LogicException
     */
    #[LiveAction]
    public function clearAll(): void
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notifications->softDeleteAllForUser($user);
    }

    #[LiveAction]
    public function suggest(): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->suggestions = [];

            return;
        }
        $q = trim($this->query);
        if ($q === '' || mb_strlen($q) < 2) {
            $this->suggestions = [];

            return;
        }
        $results = $this->users->findAllMatching($q);
        $results = array_slice($results, 0, 10);
        $this->suggestions = array_values(array_map(static fn(User $u): array => [
            'email' => $u->getUserIdentifier(),
            'name' => $u->getName(),
        ], $results));
    }

    private function findOwnedNotification(string $id): ?Notification
    {
        /** @var User $user */
        $user = $this->getUser();
        /** @var Notification|null $notification */
        $notification = $this->em->getRepository(Notification::class)->find($id);
        if ($notification === null || $notification->getUser()->getId() !== $user->getId()) {
            return null;
        }

        return $notification;
    }
}
