<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Service\InAppNotificationService;
use App\Entity\MessageStatus;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Entity\UserMessage;
use App\Repository\UserMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly InAppNotificationService $inAppNotifications,
        private readonly NotifierInterface $notifier,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
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
            // Keep an internal audit trail...
            $currentNotes = $message->getAdminNotes();
            $message->setAdminNotes($currentNotes . "\n\nAdmin Reply: " . $this->replyContent);

            // ...and actually deliver the reply to the user, by email and in-app.
            $recipient = $message->getUser();

            $subject = $this->translator->trans('admin_message_reply.subject', [
                'subject' => $message->getSubject(),
            ], 'emails');
            $content = $this->translator->trans('admin_message_reply.content', [
                'name' => $recipient->getName(),
                'subject' => $message->getSubject(),
                'reply' => $this->replyContent,
            ], 'emails');

            $notification = new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content);
            $this->notifier->send($notification, new Recipient($recipient->getEmail()));

            $this->inAppNotifications->notify(
                $recipient,
                $this->translator->trans('notifications.in_app.admin_reply.title', [], 'messages'),
                $this->translator->trans('notifications.in_app.admin_reply.body', [
                    'subject' => $message->getSubject(),
                ], 'messages'),
                $this->urlGenerator->generate('dashboard'),
                NotificationSeverity::Info,
            );
        }

        $this->entityManager->flush();
        $this->replyContent = '';
        $this->addFlash('success', 'Odpowiedź wysłana pomyślnie.');
    }
}
