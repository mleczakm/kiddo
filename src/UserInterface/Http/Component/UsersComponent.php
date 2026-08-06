<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class UsersComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public ?string $composingForUserId = null;

    #[LiveProp(writable: true)]
    public string $notifyTitle = '';

    #[LiveProp(writable: true)]
    public string $notifyBody = '';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly InAppNotificationService $inAppNotifications,
    ) {}

    /**
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->userRepository->findAllMatching($this->query);
    }

    public function getComposingForUser(): ?User
    {
        if ($this->composingForUserId === null) {
            return null;
        }

        return $this->userRepository->find((int) $this->composingForUserId);
    }

    #[LiveAction]
    public function startCompose(#[LiveArg] string $userId): void
    {
        $this->composingForUserId = $userId;
        $this->notifyTitle = '';
        $this->notifyBody = '';
    }

    #[LiveAction]
    public function cancelCompose(): void
    {
        $this->composingForUserId = null;
        $this->notifyTitle = '';
        $this->notifyBody = '';
    }

    #[LiveAction]
    public function sendNotification(): void
    {
        $recipient = $this->getComposingForUser();
        if ($recipient === null || trim($this->notifyTitle) === '') {
            return;
        }

        $this->inAppNotifications->notify(
            $recipient,
            $this->notifyTitle,
            $this->notifyBody !== '' ? $this->notifyBody : null,
            null,
            NotificationSeverity::Info,
        );

        $this->addFlash('success', sprintf('Powiadomienie wysłane do %s.', $recipient->getName()));
        $this->cancelCompose();
    }
}
