<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Event\ActivityOccurred;
use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            ActivityOccurred::class => 'onActivityOccurred',
        ];
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
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
        $this->entityManager->flush();
    }
}
