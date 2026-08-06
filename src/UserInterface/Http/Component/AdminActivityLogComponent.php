<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\MessageType;
use App\Entity\UserMessage;
use App\Repository\UserMessageRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AdminActivityLogComponent
{
    use DefaultActionTrait;

    public function __construct(
        private readonly UserMessageRepository $userMessageRepository,
    ) {}

    /**
     * Recent customer-triggered events (cancellations, reschedules, etc.),
     * sourced from the UserMessage records the booking handlers create.
     *
     * @return list<array<string, string>>
     */
    public function getActivityLog(): array
    {
        return array_values(array_map(
            $this->formatMessage(...),
            $this->userMessageRepository->findRecentMessages(8),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function formatMessage(UserMessage $message): array
    {
        return [
            'type' => $message->getType() === MessageType::CANCELLATION_REQUEST ? 'cancelled' : 'other',
            'badge' => match ($message->getType()) {
                MessageType::CANCELLATION_REQUEST => 'Odwołane zajęcia',
                MessageType::RESCHEDULE_REQUEST => 'Zmieniono termin',
                MessageType::REFUND_REQUEST => 'Prośba o zwrot',
                MessageType::COMPLAINT => 'Skarga',
                MessageType::BOOKING_ISSUE => 'Problem z rezerwacją',
                MessageType::TECHNICAL_ISSUE => 'Problem techniczny',
                MessageType::GENERAL => 'Wiadomość',
            },
            'name' => $message->getUser()
                ->getName(),
            'lesson' => $message->getSubject(),
            'notes' => $message->getMessage(),
        ];
    }
}
