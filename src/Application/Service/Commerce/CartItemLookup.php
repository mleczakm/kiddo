<?php

declare(strict_types=1);

namespace App\Application\Service\Commerce;

use App\Application\Repository\ChildRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Domain\Commerce\Cart\CartItem;
use App\Entity\Child;
use App\Entity\Lesson;
use Symfony\Component\Uid\Ulid;

final readonly class CartItemLookup
{
    public function __construct(
        private LessonRepositoryInterface $lessonRepository,
        private ChildRepositoryInterface $childRepository,
    ) {}

    /** @param list<CartItem> $items
     * @return array<string, Lesson>
     */
    public function lessons(array $items): array
    {
        if ($items === []) {
            return [];
        }
        $lessons = [];
        foreach ($this->lessonRepository->findBy([
            'id' => array_map(static fn(CartItem $item): Ulid => $item->lessonId, $items),
        ]) as $lesson) {
            $lessons[(string) $lesson->getId()] = $lesson;
        }
        return $lessons;
    }

    /** @param list<CartItem> $items
     * @return array<string, Child>
     */
    public function participants(array $items): array
    {
        $ids = array_values(array_filter(array_map(static fn(CartItem $item): ?Ulid => $item->participantId, $items)));
        if ($ids === []) {
            return [];
        }
        $participants = [];
        foreach ($this->childRepository->findBy(['id' => $ids]) as $participant) {
            $participants[(string) $participant->getId()] = $participant;
        }
        return $participants;
    }
}
