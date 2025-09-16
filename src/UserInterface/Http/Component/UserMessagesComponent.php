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

#[AsLiveComponent]
class UserMessagesComponent extends AbstractController
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
    public string $adminNotes = '';

    /**
     * @return array<UserMessage>
     */
    public function getMessages(): array
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
    public function selectMessage(#[LiveArg] string $messageId): void
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
    public function updateStatus(#[LiveArg] string $messageId, #[LiveArg] string $status): void
    {
        $message = $this->userMessageRepository->find(Ulid::fromString($messageId));
        if (! $message) {
            return;
        }

        $messageStatus = MessageStatus::from($status);
        $message->setStatus($messageStatus);

        if ($this->adminNotes) {
            $message->setAdminNotes($this->adminNotes);
        }

        $this->entityManager->flush();
        $this->adminNotes = '';
    }

    #[LiveAction]
    public function closeMessageDetails(): void
    {
        $this->selectedMessageId = null;
        $this->adminNotes = '';
    }

    public function getStatusBadgeClass(MessageStatus $status): string
    {
        return match ($status) {
            MessageStatus::UNREAD => 'bg-red-100 text-red-800',
            MessageStatus::READ => 'bg-blue-100 text-blue-800',
            MessageStatus::IN_PROGRESS => 'bg-yellow-100 text-yellow-800',
            MessageStatus::RESOLVED => 'bg-green-100 text-green-800',
            MessageStatus::ARCHIVED => 'bg-gray-100 text-gray-800',
        };
    }

    public function getTypeBadgeClass(string $type): string
    {
        return match ($type) {
            'booking_issue', 'cancellation_request', 'reschedule_request', 'refund_request' => 'bg-orange-100 text-orange-800',
            'complaint' => 'bg-red-100 text-red-800',
            'technical_issue' => 'bg-purple-100 text-purple-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function formatMessageType(string $type): string
    {
        return match ($type) {
            'general' => 'Ogólne',
            'booking_issue' => 'Problem z rezerwacją',
            'cancellation_request' => 'Prośba o anulowanie',
            'reschedule_request' => 'Prośba o przełożenie',
            'refund_request' => 'Prośba o zwrot',
            'complaint' => 'Reklamacja',
            'technical_issue' => 'Problem techniczny',
            default => 'Nieznany',
        };
    }

    public function formatStatus(MessageStatus $status): string
    {
        return match ($status) {
            MessageStatus::UNREAD => 'Nieprzeczytana',
            MessageStatus::READ => 'Przeczytana',
            MessageStatus::IN_PROGRESS => 'W trakcie',
            MessageStatus::RESOLVED => 'Rozwiązana',
            MessageStatus::ARCHIVED => 'Zarchiwizowana',
        };
    }
}
