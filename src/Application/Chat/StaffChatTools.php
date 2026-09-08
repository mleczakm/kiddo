<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Repository\LessonRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\User;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Ulid;

/**
 * Read-only lesson tools for staff (workshop instructors — ROLE_HOST — and
 * admins): find a lesson by (fuzzy) name, list its participants, or list
 * the caller's own upcoming lessons. Answers chat questions such as
 * "podaj listę uczestników moich następnych zajęć" or
 * "ile osób jest na następnych bobasach".
 */
#[AutoconfigureTag('app.chat_tool_provider')]
final readonly class StaffChatTools implements ChatToolProviderInterface
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private LessonRepositoryInterface $lessonRepository,
        private LessonPresenter $presenter,
    ) {}

    #[\Override]
    public function definitions(): array
    {
        $scope = [
            'type' => 'string',
            'enum' => ['all', 'mine'],
            'description' => 'mine = only lessons the caller is assigned to (directly or via the series). Default all.',
        ];

        return [
            new ToolDefinition(
                'staff.find_lessons',
                'Find lessons/workshops as staff (instructor or admin). Fuzzy title match — query "bobas" finds "Senso bobasy". '
                . 'Use it to resolve a name before staff.lesson_participants, or to list the caller\'s own lessons (scope=mine). '
                . 'when=next returns just the single closest upcoming match.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Free-text fragment of the lesson title, e.g. "bobas". Omit to list all.',
                        ],
                        'scope' => $scope,
                        'when' => [
                            'type' => 'string',
                            'enum' => ['next', 'upcoming', 'past', 'all'],
                            'description' => 'next = single closest upcoming; upcoming (default) = from now on; past = before now; all = no date limit.',
                        ],
                        'from' => [
                            'type' => 'string',
                            'description' => 'ISO date/datetime lower bound; overrides "when".',
                        ],
                        'to' => [
                            'type' => 'string',
                            'description' => 'ISO date/datetime upper bound; overrides "when".',
                        ],
                        'limit' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                requiresHost: true,
            ),
            new ToolDefinition(
                'staff.lesson_participants',
                'List the participants (attendees) of one lesson as staff. Pass lesson_id from staff.find_lessons, OR query + when to resolve by name in one call '
                . '(e.g. query="bobas", when="next" for "ile osób na następnych bobasach"). Cancelled bookings are excluded unless include_cancelled=true.',
                [
                    'type' => 'object',
                    'properties' => [
                        'lesson_id' => [
                            'type' => 'string',
                            'description' => 'ULID of the lesson (preferred).',
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Fuzzy title fragment, used when lesson_id is not given.',
                        ],
                        'when' => [
                            'type' => 'string',
                            'enum' => ['next', 'last'],
                            'description' => 'Which occurrence to pick when resolving by query: next upcoming (default) or most recent past.',
                        ],
                        'scope' => $scope,
                        'include_cancelled' => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
                requiresHost: true,
            ),
        ];
    }

    #[\Override]
    public function supports(string $name): bool
    {
        return str_starts_with($name, 'staff.');
    }

    #[\Override]
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        try {
            $args = new ToolArguments($arguments);

            return match ($name) {
                'staff.find_lessons' => $this->findLessons($actor, $args),
                'staff.lesson_participants' => $this->lessonParticipants($actor, $args),
                default => ToolResult::failure(sprintf('Unknown staff tool: %s', $name)),
            };
        } catch (\InvalidArgumentException $e) {
            return ToolResult::failure($e->getMessage());
        }
    }

    /**
     * @throws \InvalidArgumentException on malformed date arguments — caught in {@see call()}.
     */
    private function findLessons(ChatActor $actor, ToolArguments $args): ToolResult
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
        $lessons = $this->lessonRepository->findForStaff(
            $query,
            $from,
            $to,
            $this->instructorFilter($actor, $args),
            $fetchLimit,
        );
        if ($reverse) {
            $lessons = array_slice(array_reverse($lessons), 0, $limit);
        }

        $items = array_map(fn(Lesson $lesson): array => $this->lessonRow($actor, $lesson), $lessons);

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
     * @throws \InvalidArgumentException on malformed lesson_id / missing selector — caught in {@see call()}.
     */
    private function lessonParticipants(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $lesson = $this->resolveLesson($actor, $args);
        if ($lesson === null) {
            return ToolResult::failure(
                'Lesson not found. Use staff.find_lessons to look it up.',
                'Nie znalazłem takich zajęć. Sprawdź nazwę przez staff.find_lessons.',
            );
        }

        $includeCancelled = $args->bool('include_cancelled');
        $participants = [];
        foreach ($lesson->getBookings() as $booking) {
            $cancelled = $this->isOccurrenceCancelled($booking, $lesson);
            if ($cancelled && !$includeCancelled) {
                continue;
            }
            $participants[] = $this->participantRow($booking, $lesson, $cancelled);
        }

        $data = $this->presenter->lesson($lesson);

        return ToolResult::success(
            sprintf(
                '%s (%s) — %d uczestników.',
                $data['title'],
                $lesson->schedule->format('d.m.Y H:i'),
                count($participants),
            ),
            [
                ...$data,
                'count' => count($participants),
                'participants' => $participants,
            ],
        );
    }

    /**
     * @throws \InvalidArgumentException on a malformed lesson_id or when
     *         neither lesson_id nor query is given — caught in {@see call()}.
     */
    private function resolveLesson(ChatActor $actor, ToolArguments $args): ?Lesson
    {
        if ($args->has('lesson_id')) {
            $raw = trim($args->requireString('lesson_id'));
            if (!Ulid::isValid($raw)) {
                throw new \InvalidArgumentException('Invalid lesson_id ULID');
            }

            return $this->lessonRepository->find(Ulid::fromString($raw));
        }

        $query = $args->string('query');
        if ($query === null) {
            throw new \InvalidArgumentException('Provide lesson_id or query');
        }

        $instructor = $this->instructorFilter($actor, $args);
        $now = Clock::get()->now();
        if (($args->string('when', 'next') ?? 'next') === 'last') {
            // Repo orders ASC; the most recent past occurrence is the last row.
            $past = $this->lessonRepository->findForStaff($query, null, $now, $instructor, 100);

            return $past === [] ? null : $past[array_key_last($past)];
        }

        return $this->lessonRepository->findForStaff($query, $now, null, $instructor, 1)[0] ?? null;
    }

    private function instructorFilter(ChatActor $actor, ToolArguments $args): ?User
    {
        return ($args->string('scope', 'all') ?? 'all') === 'mine' ? $actor->requireUser() : null;
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable, 2: bool} [from, to, reverseResults]
     *
     * @throws \InvalidArgumentException on an unparseable from/to argument — caught in {@see call()}.
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

    /**
     * @return array<string, mixed>
     */
    private function participantRow(Booking $booking, Lesson $lesson, bool $cancelled): array
    {
        $user = $booking->getUser();

        return [
            'booking_id' => (string) $booking->getId(),
            'participant' => $booking->getChild()?->getName() ?? $user->getName(),
            'child_name' => $booking->getChild()?->getName(),
            'parent_name' => $user->getName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() !== null ? (string) $user->getPhone() : null,
            'status' => $booking->getStatus(),
            'payment_status' => $booking->getPayment()?->getStatus(),
            'occurrence_cancelled' => $cancelled,
            'occurrence_rescheduled' => $booking->isLessonRescheduled($lesson),
            'notes' => $booking->getNotes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonRow(ChatActor $actor, Lesson $lesson): array
    {
        $active = 0;
        foreach ($lesson->getBookings() as $booking) {
            if ($this->isOccurrenceCancelled($booking, $lesson)) {
                continue;
            }
            ++$active;
        }

        return [
            ...$this->presenter->lesson($lesson),
            'bookings_count' => $lesson->getBookings()->count(),
            'active_participants' => $active,
            'is_mine' => $this->isMine($actor, $lesson),
            'instructors' => array_map(
                static fn(User $instructor): string => $instructor->getName(),
                $lesson->getAllInstructors(),
            ),
        ];
    }

    private function isMine(ChatActor $actor, Lesson $lesson): bool
    {
        if ($actor->isGuest()) {
            return false;
        }
        $userId = $actor->requireUser()->getId();
        foreach ($lesson->getAllInstructors() as $instructor) {
            if ($instructor->getId() === $userId) {
                return true;
            }
        }

        return false;
    }

    private function isOccurrenceCancelled(Booking $booking, Lesson $lesson): bool
    {
        return $booking->getStatus() === Booking::STATUS_CANCELLED || $booking->isLessonCancelled($lesson);
    }
}
