<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Repository\LessonSearchInterface;
use App\Entity\User;
use Symfony\Component\Clock\Clock;

final readonly class StaffLessonSearch
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private LessonSearchInterface $lessonSearch,
        private StaffLessonPresenter $staffPresenter,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function find(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $query = $args->string('query');
        $when = $args->string('when', 'upcoming') ?? 'upcoming';
        $limit = max(1, min($args->int('limit', self::DEFAULT_LIMIT) ?? self::DEFAULT_LIMIT, 100));
        [$from, $to, $reverse] = $this->window($when, $args);

        // The repo always orders ASC. "next" wants a single earliest upcoming
        // row; "past" wants the most recent rows, so over-fetch then reverse.
        $fetchLimit = match ($when) {
            'next' => 1,
            'past' => 100,
            default => $limit,
        };
        $lessons = $this->lessonSearch->findForStaff(
            $query,
            $from,
            $to,
            $this->instructorFilter($actor, $args),
            $fetchLimit,
        );
        if ($reverse) {
            $lessons = array_slice(array_reverse($lessons), 0, $limit);
        }

        $items = [];
        foreach ($lessons as $lesson) {
            $items[] = $this->staffPresenter->lessonRow($actor, $lesson);
        }

        return ToolResult::success(
            sprintf('Znaleziono %d zajęć%s.', count($items), $query !== null ? sprintf(' dla „%s”', $query) : ''),
            [
                'scope' => $args->string('scope', 'all'),
                'when' => $when,
                'query' => $query,
                'lessons' => $items,
            ],
        );
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function instructorFilter(ChatActor $actor, ToolArguments $args): ?User
    {
        return ($args->string('scope', 'all') ?? 'all') === 'mine' ? $actor->requireUser() : null;
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable, 2: bool} [from, to, reverseResults]
     *
     * @throws \InvalidArgumentException
     */
    private function window(string $when, ToolArguments $args): array
    {
        $from = $args->has('from') ? new \DateTimeImmutable($args->requireString('from')) : null;
        $to = $args->has('to') ? new \DateTimeImmutable($args->requireString('to')) : null;
        if ($from !== null || $to !== null) {
            return [$from, $to, false];
        }

        $now = Clock::get()->now();

        return match ($when) {
            'past' => [null, $now, true],
            'all' => [null, null, false],
            default => [$now, null, false],
        };
    }
}
