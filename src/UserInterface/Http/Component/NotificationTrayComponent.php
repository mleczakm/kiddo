<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationTrayComponent extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/u/notifications/tray', name: 'notifications_tray', methods: ['GET'])]
    public function tray(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $items = $this->notifications->findRecentForUser($user, 30);

        return $this->render('components/notification/tray.html.twig', [
            'items' => $items,
        ]);
    }

    #[Route('/u/notifications/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $count = $this->notifications->countUnreadForUser($user);
        return $this->json([
            'count' => $count,
        ]);
    }

    #[Route('/u/notifications/{id}/read', name: 'notifications_mark_read', requirements: [
        'id' => '[A-Za-z0-9]+',
    ], methods: ['POST', 'GET'])]
    public function markRead(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        /** @var Notification|null $n */
        $n = $this->em->getRepository(Notification::class)->find($id);
        if ($n && $n->getUser()->getId() === $user->getId()) {
            $n->markRead();
            $this->em->flush();
        }
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/u/notifications/{id}/delete', name: 'notifications_delete', requirements: [
        'id' => '[A-Za-z0-9]+',
    ], methods: ['POST', 'GET'])]
    public function delete(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        /** @var Notification|null $n */
        $n = $this->em->getRepository(Notification::class)->find($id);
        if ($n && $n->getUser()->getId() === $user->getId()) {
            $n->softDelete();
            $this->em->flush();
        }
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
