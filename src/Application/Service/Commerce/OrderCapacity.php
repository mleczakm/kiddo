<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Application\Service\BookingFactory;
use App\Entity\Lesson;
use App\Entity\TicketType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class OrderCapacity
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param list<OrderItemSelection> $items
     * @throws \App\Application\Service\Commerce\CapacityExceededException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \InvalidArgumentException
     * @throws \Doctrine\DBAL\Exception
     */
    public function lockAndValidate(array $items): void
    {
        $requirements = $this->requirements($items);
        if ($requirements === []) {
            return;
        }

        $ids = array_map(static fn($requirement): string => $requirement['lesson']
            ->getId()
            ->toRfc4122(), array_values($requirements));
        /** @var list<Lesson> $lockedLessons */
        $lockedLessons = $this->em
            ->createQueryBuilder()
            ->select('lesson')
            ->from(Lesson::class, 'lesson')
            ->where('lesson.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('lesson.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
        $lessons = [];
        foreach ($lockedLessons as $lesson) {
            $lessons[(string) $lesson->getId()] = $lesson;
        }
        $occupied = $this->occupiedSeats($ids);

        foreach ($requirements as $id => $requirement) {
            $lesson = $lessons[$id] ?? null;
            if (!$lesson instanceof Lesson) {
                throw new \InvalidArgumentException(sprintf('Lesson %s no longer exists.', $id));
            }
            if (
                !$lesson->future()
                || !$lesson->isActive()
                || !$lesson->isPubliclyVisible()
                || ($lesson->getMetadata()->capacity - (int) ($occupied[$lesson->getId()->toRfc4122()] ?? 0))
                    < $requirement['required']
            ) {
                throw new CapacityExceededException(sprintf('Lesson %s has insufficient capacity.', $id));
            }
        }
    }

    /**
     * @param list<OrderItemSelection> $items
     * @return array<string, array{lesson: Lesson, required: int}>
     */
    private function requirements(array $items): array
    {
        /** @var array<string, array{lesson: Lesson, required: int}> $requirements */
        $requirements = [];
        foreach ($items as $item) {
            $occurrences = match ($item->ticketOption->type) {
                TicketType::CARNET_4 => $item->lesson->getSeries()?->getLessonsGte($item->lesson, 4) ?? [],
                TicketType::MONTHLY => BookingFactory::monthLessons($item->lesson),
                TicketType::ONE_TIME => [$item->lesson],
            };
            foreach ($occurrences as $lesson) {
                $id = (string) $lesson->getId();
                $requirements[$id] ??= ['lesson' => $lesson, 'required' => 0];
                ++$requirements[$id]['required'];
            }
        }

        return $requirements;
    }

    /**
     * @param list<string> $lessonIds
     * @return array<string, int>
     * @throws \Doctrine\DBAL\Exception
     */
    private function occupiedSeats(array $lessonIds): array
    {
        /** @var array<string, int|string> $rows */
        $rows = $this->em->getConnection()->fetchAllKeyValue(<<<'SQL'
                SELECT occurrence.lesson_id, COUNT(*)
            FROM booking_occurrence occurrence
            INNER JOIN booking b ON b.id = occurrence.booking_id
            WHERE occurrence.lesson_id IN (:lesson_ids)
              AND occurrence.status IN ('reserved', 'confirmed')
              AND b.status IN ('pending', 'active', 'waiting_approval')
            GROUP BY occurrence.lesson_id
            SQL, [
            'lesson_ids' => $lessonIds,
        ], ['lesson_ids' => ArrayParameterType::STRING]);
        return array_map(static fn(int|string $count): int => (int) $count, $rows);
    }
}
