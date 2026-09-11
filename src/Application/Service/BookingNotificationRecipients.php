<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\FinanceContactRepositoryInterface;
use App\Entity\FinanceContact;
use App\Entity\Lesson;
use App\Entity\User;

/**
 * Resolves the internal audience for booking and payment information.
 *
 * Financial contacts receive the financial/operational copy, while lesson
 * instructors receive information only for occurrences they are assigned to
 * directly or through the workshop series.
 */
final readonly class BookingNotificationRecipients
{
    public function __construct(
        private FinanceContactRepositoryInterface $financeContacts,
        private LessonInstructorResolver $instructorResolver,
    ) {}

    /**
     * @return list<User>
     */
    public function financeContacts(?User $exclude = null): array
    {
        $users = array_map(
            static fn(FinanceContact $contact): User => $contact->getUser(),
            $this->financeContacts->findAll(),
        );

        return $this->deduplicate($users, $exclude);
    }

    /**
     * @param iterable<Lesson> $lessons
     * @return list<User>
     */
    public function forLessons(iterable $lessons, ?User $exclude = null): array
    {
        $lessonList = is_array($lessons) ? $lessons : iterator_to_array($lessons, false);

        return $this->deduplicate([
            ...$this->financeContacts(),
            ...$this->instructorResolver->resolve($lessonList),
        ], $exclude);
    }

    /**
     * @param iterable<User> $users
     * @return list<User>
     */
    private function deduplicate(iterable $users, ?User $exclude = null): array
    {
        $result = [];
        $seen = [];
        $excludedId = $exclude?->getId();

        foreach ($users as $user) {
            $id = $user->getId();
            if ($excludedId !== null && $id === $excludedId) {
                continue;
            }

            $key = $id === null ? 'email:' . mb_strtolower($user->getEmailString()) : 'id:' . $id;
            if ($seen[$key] ?? false) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $user;
        }

        return $result;
    }
}
