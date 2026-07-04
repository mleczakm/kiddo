<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\MessageStatus;
use App\Entity\User;
use App\Entity\UserMessage;
use App\Repository\UserMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('AdminMessagesComponent', template: 'components/AdminMessagesComponent.html.twig')]
class AdminMessagesComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly UserMessageRepository $userMessageRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[LiveProp(writable: true)]
    public string $activeFilter = 'unread';

    #[LiveProp(writable: true)]
    public ?string $selectedMessageId = null;

    #[LiveProp(writable: true)]
    public string $replyContent = '';

    #[LiveProp(writable: true)]
    public string $statusChange = 'in_progress';

    /**
     * @return array<UserMessage>
     */
    public function getFilteredMessages(): array
    {
        return match ($this->activeFilter) {
            'unread' => $this->userMessageRepository->findByStatus(MessageStatus::UNREAD),
            'read' => $this->userMessageRepository->findByStatus(MessageStatus::READ),
            'in_progress' => $this->userMessageRepository->findByStatus(MessageStatus::IN_PROGRESS),
            'resolved' => $this->userMessageRepository->findByStatus(MessageStatus::RESOLVED),
            'all' => $this->userMessageRepository->findRecentMessages(50),
            default => $this->userMessageRepository->findByStatus(MessageStatus::UNREAD),
        };
    }

    public function getUnreadCount(): int
    {
        return $this->userMessageRepository->countUnreadMessages();
    }

    public function getSelectedMessage(): ?UserMessage
    {
        if ($this->selectedMessageId === null) {
            return null;
        }

        return $this->userMessageRepository->find(
            $this->selectedMessageId ? Ulid::fromString($this->selectedMessageId) : null
        );
    }

    #[LiveAction]
    public function changeFilter(#[LiveArg] string $filter): void
    {
        $this->activeFilter = $filter;
        $this->selectedMessageId = null;
    }

    #[LiveAction]
    public function openMessage(#[LiveArg] string $messageId): void
    {
        $this->selectedMessageId = $messageId;
        $message = $this->userMessageRepository->find(Ulid::fromString($messageId));

        if ($message && $message->isUnread()) {
            $user = $this->getUser();
            if ($user instanceof User) {
                $message->markAsRead($user);
                $this->entityManager->flush();
            }
        }
    }

    #[LiveAction]
    public function closeMessage(): void
    {
        $this->selectedMessageId = null;
        $this->replyContent = '';
    }

    #[LiveAction]
    public function sendReply(): void
    {
        $message = $this->userMessageRepository->find(Ulid::fromString($this->selectedMessageId));
        if (! $message) {
            return;
        }

        $messageStatus = MessageStatus::from($this->statusChange);
        $message->setStatus($messageStatus);

        if ($this->replyContent) {
            // In a real implementation, you would send the reply here
            // For now, we'll just add it as admin notes
            $currentNotes = $message->getAdminNotes();
            $message->setAdminNotes($currentNotes . "\n\nAdmin Reply: " . $this->replyContent);
        }

        $this->entityManager->flush();
        $this->replyContent = '';
        $this->addFlash('success', 'Odpowiedź wysłana pomyślnie.');
    }
}
