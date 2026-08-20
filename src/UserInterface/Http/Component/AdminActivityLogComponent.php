<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Infrastructure\Doctrine\Repository\ActivityLogRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * "Ostatnia aktywność" — a live, polling feed of everything customer-
 * facing that just happened: registrations, bookings, cancellations,
 * reschedules, payments, unmatched transfers, newsletter signups, and
 * customer messages. Sourced from the activity_log table, written to by
 * ActivityLogMiddleware (command-bus actions) and direct ActivityLogger
 * calls (actions that mutate entities without going through the bus).
 */
#[AsLiveComponent]
final readonly class AdminActivityLogComponent
{
    use DefaultActionTrait;

    private const int LIMIT = 10;

    public function __construct(
        private ActivityLogRepository $activityLogRepository,
    ) {}

    /**
     * @return list<array{
     *     type: string,
     *     icon: string,
     *     color: string,
     *     title: string,
     *     name: ?string,
     *     summary: ?string,
     *     url: ?string,
     *     createdAt: \DateTimeImmutable,
     * }>
     */
    public function getActivityLog(): array
    {
        return array_values(array_map($this->format(...), $this->activityLogRepository->findRecent(self::LIMIT)));
    }

    /**
     * No-op action: LiveComponents re-render (and re-run getActivityLog())
     * after every action, so this is all `data-poll` needs to call to
     * pick up new rows written since the last render.
     */
    #[LiveAction]
    public function refresh(): void {}

    /**
     * @return array{
     *     type: string,
     *     icon: string,
     *     color: string,
     *     title: string,
     *     name: ?string,
     *     summary: ?string,
     *     url: ?string,
     *     createdAt: \DateTimeImmutable,
     * }
     */
    private function format(ActivityLog $log): array
    {
        return [
            'type' => $log->getType()->value,
            'icon' => $this->iconFor($log->getType()),
            'color' => $this->colorFor($log->getType()),
            'title' => $log->getTitle(),
            'name' => $log->getSubject()?->getName(),
            'summary' => $log->getSummary(),
            'url' => $log->getUrl(),
            'createdAt' => $log->getCreatedAt(),
        ];
    }

    private function iconFor(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::USER_REGISTERED => 'user-plus',
            ActivityType::BOOKING_CREATED => 'calendar-plus',
            ActivityType::BOOKING_CANCELLED, ActivityType::BOOKING_AUTO_CANCELLED => 'calendar-x',
            ActivityType::BOOKING_RESCHEDULED => 'calendar-clock',
            ActivityType::BOOKING_REACTIVATED => 'calendar-check',
            ActivityType::REFUND_REQUESTED,
            ActivityType::REFUND_APPROVED,
            ActivityType::REFUND_DECLINED,
                => 'rotate-ccw',
            ActivityType::PAYMENT_RECEIVED, ActivityType::PAYMENT_MARKED_PAID => 'banknote',
            ActivityType::TRANSFER_UNMATCHED => 'alert-triangle',
            ActivityType::NEWSLETTER_SUBSCRIBED => 'mail',
            ActivityType::CUSTOMER_MESSAGE => 'message-circle',
            ActivityType::USER_PROFILE_UPDATED => 'user-cog',
        };
    }

    private function colorFor(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::USER_REGISTERED, ActivityType::NEWSLETTER_SUBSCRIBED => 'indigo',
            ActivityType::BOOKING_CREATED,
            ActivityType::BOOKING_REACTIVATED,
            ActivityType::PAYMENT_RECEIVED,
            ActivityType::PAYMENT_MARKED_PAID,
            ActivityType::REFUND_APPROVED,
                => 'emerald',
            ActivityType::BOOKING_CANCELLED, ActivityType::REFUND_REQUESTED => 'amber',
            ActivityType::BOOKING_AUTO_CANCELLED,
            ActivityType::CUSTOMER_MESSAGE,
            ActivityType::USER_PROFILE_UPDATED,
            ActivityType::REFUND_DECLINED,
                => 'slate',
            ActivityType::BOOKING_RESCHEDULED => 'blue',
            ActivityType::TRANSFER_UNMATCHED => 'red',
        };
    }
}
