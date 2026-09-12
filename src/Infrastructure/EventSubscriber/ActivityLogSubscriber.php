<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Event\ActivityOccurred;
use App\Entity\ActivityLog;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists every ActivityOccurred event as an ActivityLog row. This is the
 * one and only write path into the activity_log table, regardless of
 * whether the event originated from a Messenger command handler, a
 * workflow transition, or a direct entity mutation.
 */
final readonly class ActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogRepository $activityLogRepository,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ActivityOccurred::class => 'onActivityOccurred',
        ];
    }

    /**
     * @throws ORMException getReference() itself can still throw before the
     *     flush() below, which is the part guarded against best-effort
     *     failures.
     */
    public function onActivityOccurred(ActivityOccurred $event): void
    {
        if ($event->dedupeKey !== null && $this->activityLogRepository->existsByDedupeKey($event->dedupeKey)) {
            return;
        }

        $subject = $event->subjectId !== null
            ? $this->entityManager->getReference(User::class, $event->subjectId)
            : null;

        $this->entityManager->persist(new ActivityLog(
            type: $event->type,
            title: $event->title,
            subject: $subject,
            summary: $event->summary,
            url: $event->url,
            context: $event->context,
            dedupeKey: $event->dedupeKey,
        ));

        try {
            $this->entityManager->flush();
        } catch (ORMException|ORMInvalidArgumentException $exception) {
            // The activity feed is a best-effort observability feature, not a
            // domain guarantee. Flushing here also picks up whatever else is
            // pending in the unit of work; if that includes an entity that
            // (for reasons upstream of this subscriber, e.g. a stale
            // reference surviving a task-worker boundary) Doctrine no longer
            // considers managed, failing loudly here would roll back the
            // real business transaction (a refund, a cancellation, ...) that
            // already completed successfully. Losing one activity-log entry
            // is a much better outcome than that.
            $this->logger->error('Failed to persist activity log entry', [
                'type' => $event->type,
                'exception' => $exception,
            ]);
        }
    }
}
