<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Command\ImportTransfersFromMail;
use App\Application\Query\Lesson\TodayLessonsQuery;
use App\Application\Repository\BookingRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Application\Repository\PaymentRepositoryInterface;
use App\Application\Repository\SeriesRepositoryInterface;
use App\Application\Repository\TransferRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\NotificationSeverity;
use App\Entity\Payment;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\User;
use App\Message\CancelLessonBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Workflow\WorkflowInterface;

#[AutoconfigureTag('app.chat_tool_provider')]
final readonly class AdminChatTools implements ChatToolProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private LessonRepositoryInterface $lessonRepository,
        private BookingRepositoryInterface $bookingRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private TransferRepositoryInterface $transferRepository,
        private UserRepositoryInterface $userRepository,
        private SeriesRepositoryInterface $seriesRepository,
        private TodayLessonsQuery $todayLessonsQuery,
        private WorkflowInterface $paymentStateMachine,
        private LessonPresenter $presenter,
        private InAppNotificationService $inAppNotificationService,
    ) {}

    #[\Override]
    public function definitions(): array
    {
        $confirm = [
            'confirm' => [
                'type' => 'boolean',
                'description' => 'Must be true to execute the mutation',
            ],
        ];

        return [
            new ToolDefinition(
                'admin.today_schedule',
                'List today’s lessons with attendee counts (admin schedule).',
                [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD, defaults to today',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.list_lessons',
                'List lessons for a week (admin ops). Optional query/week. For age-filtered public catalog use user.list_upcoming_lessons.',
                [
                    'type' => 'object',
                    'properties' => [
                        'week' => [
                            'type' => 'string',
                            'description' => 'Week start YYYY-MM-DD',
                        ],
                        'include_cancelled' => [
                            'type' => 'boolean',
                        ],
                        'query' => [
                            'type' => 'string',
                        ],
                        'limit' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.get_lesson',
                'Get lesson details for admin ops.',
                [
                    'type' => 'object',
                    'properties' => [
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['lesson_id'],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.toggle_lesson',
                'Toggle lesson status between active and cancelled.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'lesson_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.update_lesson_capacity',
                'Set lesson capacity (spots).',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                        'capacity' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                    ],
                    'required' => ['confirm', 'lesson_id', 'capacity'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.list_series',
                'List series in a date range.',
                [
                    'type' => 'object',
                    'properties' => [
                        'week' => [
                            'type' => 'string',
                            'description' => 'Week start YYYY-MM-DD',
                        ],
                        'include_cancelled' => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.update_series',
                'Replace ticket options for a series.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'series_id' => [
                            'type' => 'string',
                        ],
                        'ticket_options' => [
                            'type' => 'array',
                            'description' => 'Optional full replacement of ticket options',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'enum' => ['one_time', 'carnet_4'],
                                    ],
                                    'amount' => [
                                        'type' => 'string',
                                    ],
                                    'currency' => [
                                        'type' => 'string',
                                    ],
                                    'description' => [
                                        'type' => 'string',
                                    ],
                                    'reschedule_policy' => [
                                        'type' => 'string',
                                    ],
                                ],
                                'required' => ['type', 'amount'],
                            ],
                        ],
                    ],
                    'required' => ['confirm', 'series_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.clone_template_lesson',
                'Create a new lesson occurrence by cloning a template lesson. Requires template_lesson_id ULID, schedule (ISO datetime), confirm=true. Not for listing workshops — use user.list_upcoming_lessons.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'template_lesson_id' => [
                            'type' => 'string',
                            'description' => 'ULID of an existing lesson to copy',
                        ],
                        'schedule' => [
                            'type' => 'string',
                            'description' => 'ISO datetime for the new occurrence',
                        ],
                        'capacity' => [
                            'type' => 'integer',
                        ],
                    ],
                    'required' => ['confirm', 'template_lesson_id', 'schedule'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.list_bookings',
                'Search bookings by status or user email fragment.',
                [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                        ],
                        'query' => [
                            'type' => 'string',
                        ],
                        'limit' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.create_booking',
                'Create an active booking for a user (fast booking, no payment).',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                        'email' => [
                            'type' => 'string',
                        ],
                        'name' => [
                            'type' => 'string',
                        ],
                        'notes' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'lesson_id', 'email'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.mark_booking_paid',
                'Mark booking payment as paid.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'booking_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'booking_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.cancel_lesson',
                'Cancel a lesson on any booking (admin).',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'booking_id' => [
                            'type' => 'string',
                        ],
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                        'reason' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'booking_id', 'lesson_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.refund_lesson',
                'Refund a lesson on any booking (admin).',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'booking_id' => [
                            'type' => 'string',
                        ],
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                        'reason' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'booking_id', 'lesson_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.reschedule_lesson',
                'Reschedule any booking lesson (admin).',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'booking_id' => [
                            'type' => 'string',
                        ],
                        'lesson_id' => [
                            'type' => 'string',
                        ],
                        'new_lesson_id' => [
                            'type' => 'string',
                        ],
                        'reason' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'booking_id', 'lesson_id', 'new_lesson_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.list_payments',
                'List pending payments, optional search.',
                [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.list_unmatched_transfers',
                'List bank transfers not yet assigned to a payment.',
                [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.assign_transfer',
                'Assign an unmatched transfer to a pending payment and mark paid.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'transfer_id' => [
                            'type' => 'integer',
                        ],
                        'payment_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'transfer_id', 'payment_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.reject_transfer',
                'Reject/delete an unmatched transfer.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'transfer_id' => [
                            'type' => 'integer',
                        ],
                    ],
                    'required' => ['confirm', 'transfer_id'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.trigger_import_transfers',
                'Trigger IMAP bank-transfer import.',
                [
                    'type' => 'object',
                    'properties' => [...$confirm],
                    'required' => ['confirm'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'admin.search_users',
                'Search users by name or email.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['query'],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.get_user',
                'Get user profile, children and booking summary.',
                [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => [
                            'type' => 'integer',
                        ],
                        'email' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                requiresAdmin: true,
            ),
            new ToolDefinition(
                'admin.notify_user',
                'Send an in-app notification to a user.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'user_id' => [
                            'type' => 'integer',
                        ],
                        'email' => [
                            'type' => 'string',
                        ],
                        'title' => [
                            'type' => 'string',
                        ],
                        'body' => [
                            'type' => 'string',
                        ],
                        'url' => [
                            'type' => 'string',
                        ],
                        'severity' => [
                            'type' => 'string',
                            'enum' => array_map(
                                static fn(NotificationSeverity $s) => $s->value,
                                NotificationSeverity::cases(),
                            ),
                        ],
                    ],
                    'required' => ['confirm', 'title'],
                ],
                requiresAdmin: true,
                requiresConfirm: true,
            ),
        ];
    }

    #[\Override]
    public function supports(string $name): bool
    {
        return str_starts_with($name, 'admin.');
    }

    #[\Override]
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        try {
            $args = new ToolArguments($arguments);

            return match ($name) {
                'admin.today_schedule' => $this->todaySchedule($args),
                'admin.list_lessons' => $this->listLessons($args),
                'admin.get_lesson' => $this->getLesson($args),
                'admin.toggle_lesson' => $this->toggleLesson($args),
                'admin.update_lesson_capacity' => $this->updateCapacity($args),
                'admin.list_series' => $this->listSeries($args),
                'admin.update_series' => $this->updateSeries($args),
                'admin.clone_template_lesson' => $this->cloneTemplateLesson($args),
                'admin.list_bookings' => $this->listBookings($args),
                'admin.create_booking' => $this->createBooking($args),
                'admin.mark_booking_paid' => $this->markBookingPaid($args),
                'admin.cancel_lesson' => $this->cancelLesson($actor, $args),
                'admin.refund_lesson' => $this->refundLesson($actor, $args),
                'admin.reschedule_lesson' => $this->rescheduleLesson($actor, $args),
                'admin.list_payments' => $this->listPayments($args),
                'admin.list_unmatched_transfers' => $this->listUnmatchedTransfers(),
                'admin.assign_transfer' => $this->assignTransfer($args),
                'admin.reject_transfer' => $this->rejectTransfer($args),
                'admin.trigger_import_transfers' => $this->triggerImport(),
                'admin.search_users' => $this->searchUsers($args),
                'admin.get_user' => $this->getUser($args),
                'admin.notify_user' => $this->notifyUser($args),
                default => ToolResult::failure(sprintf('Unknown admin tool: %s', $name)),
            };
        } catch (\InvalidArgumentException $e) {
            return ToolResult::failure($e->getMessage());
        }
    }

    private function todaySchedule(ToolArguments $args): ToolResult
    {
        $date = $args->has('date')
            ? new \DateTimeImmutable($args->requireString('date'))
            : new \DateTimeImmutable('today');
        $lessons = $this->todayLessonsQuery->forDate($date);
        $items = [];
        foreach ($lessons as $lesson) {
            $data = $this->presenter->lesson($lesson);
            $data['bookings_count'] = $lesson->getBookings()->count();
            $items[] = $data;
        }

        return ToolResult::success(sprintf('Zajęcia %s: %d.', $date->format('Y-m-d'), count($items)), [
            'date' => $date->format('Y-m-d'),
            'lessons' => $items,
        ]);
    }

    private function listLessons(ToolArguments $args): ToolResult
    {
        $week = $args->string('week') ?? new \DateTimeImmutable('today')->format('Y-m-d');
        $query = $args->string('query');
        $limit = $args->int('limit', 50) ?? 50;
        $includeCancelled = $args->bool('include_cancelled');

        $lessons = $this->lessonRepository->findByFilters($query, null, $week, $limit);
        if ($includeCancelled) {
            // findByFilters only returns active; load cancelled in the same week window separately
            $weekStart = new \DateTimeImmutable($week);
            $weekEnd = $weekStart->modify('+7 days 23:59:59');
            $cancelled = $this->lessonRepository->findCancelledInRange($weekStart, $weekEnd);
            $lessons = array_merge($lessons, $cancelled);
        }

        $items = array_map($this->presenter->lesson(...), $lessons);

        return ToolResult::success(sprintf('Lekcje w tygodniu od %s: %d.', $week, count($items)), [
            'week' => $week,
            'lessons' => $items,
        ]);
    }

    private function getLesson(ToolArguments $args): ToolResult
    {
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($lesson === null) {
            return ToolResult::failure('Lesson not found');
        }
        $data = $this->presenter->lesson($lesson);
        $attendees = [];
        foreach ($lesson->getBookings() as $booking) {
            if (in_array($booking->getStatus(), [Booking::STATUS_CANCELLED], true)) {
                continue;
            }
            $attendees[] = [
                'booking_id' => (string) $booking->getId(),
                'user' => $booking->getUser()->getName(),
                'email' => $booking->getUser()->getEmail(),
                'status' => $booking->getStatus(),
                'payment_status' => $booking->getPayment()?->getStatus(),
            ];
        }

        return ToolResult::success(sprintf('%s — %d uczestników.', $data['title'], count($attendees)), [
            ...$data,
            'attendees' => $attendees,
        ]);
    }

    private function toggleLesson(ToolArguments $args): ToolResult
    {
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($lesson === null) {
            return ToolResult::failure('Lesson not found');
        }
        $lesson->status = $lesson->status === 'active' ? 'cancelled' : 'active';
        $this->entityManager->flush();

        return ToolResult::success(
            sprintf('Status lekcji ustawiony na %s.', $lesson->status),
            $this->presenter->lesson($lesson),
        );
    }

    private function updateCapacity(ToolArguments $args): ToolResult
    {
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($lesson === null) {
            return ToolResult::failure('Lesson not found');
        }
        $capacity = $args->requireInt('capacity');
        if ($capacity < 1) {
            return ToolResult::failure('Capacity must be >= 1');
        }
        $lesson->setMetadata($lesson->getMetadata()->withCapacity($capacity));
        $this->entityManager->flush();

        return ToolResult::success(
            sprintf('Ustawiono pojemność na %d (wolne: %d).', $capacity, $lesson->getAvailableSpots()),
            $this->presenter->lesson($lesson),
        );
    }

    private function listBookings(ToolArguments $args): ToolResult
    {
        $bookings = $this->bookingRepository->findFiltered(
            $args->string('status'),
            $args->string('query'),
            $args->int('limit', 30) ?? 30,
        );
        $items = array_map($this->presenter->booking(...), $bookings);

        return ToolResult::success(sprintf('Rezerwacje: %d.', count($items)), [
            'bookings' => $items,
        ]);
    }

    private function createBooking(ToolArguments $args): ToolResult
    {
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($lesson === null) {
            return ToolResult::failure('Lesson not found');
        }
        $email = strtolower(trim($args->requireString('email')));
        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if (!$user instanceof User) {
            $user = new User($email, $args->string('name') ?? $email);
            $this->entityManager->persist($user);
        }
        $booking = new Booking($user, null, $lesson);
        $booking->setStatus(Booking::STATUS_ACTIVE);
        if ($args->has('notes')) {
            $booking->setNotes($args->requireString('notes'));
        }
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        return ToolResult::success(
            sprintf('Utworzono aktywną rezerwację dla %s.', $email),
            $this->presenter->booking($booking),
        );
    }

    private function markBookingPaid(ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        if ($booking === null) {
            return ToolResult::failure('Booking not found');
        }
        $payment = $booking->getPayment();
        if ($payment === null) {
            $payment = new Payment($booking->getUser(), Money::of(0, 'PLN'));
            $booking->payment = $payment;
            $this->entityManager->persist($payment);
        }
        if ($this->paymentStateMachine->can($payment, Payment::TRANSITION_PAY)) {
            $this->paymentStateMachine->apply($payment, Payment::TRANSITION_PAY);
        } else {
            $payment->setStatus(Payment::STATUS_PAID);
        }
        if ($booking->getStatus() === Booking::STATUS_PENDING) {
            $booking->setStatus(Booking::STATUS_ACTIVE);
        }
        $this->entityManager->flush();

        return ToolResult::success('Płatność oznaczona jako opłacona.', $this->presenter->booking($booking));
    }

    private function cancelLesson(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($booking === null || $lesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        $this->bus->dispatch(
            new CancelLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $actor->requireUser(),
                $args->string('reason'),
            ),
        );

        return ToolResult::success('Lekcja odwołana (admin).');
    }

    private function refundLesson(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($booking === null || $lesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        $this->bus->dispatch(
            new RefundLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $actor->requireUser(),
                $args->string('reason'),
            ),
        );

        return ToolResult::success('Zwrot zlecony (admin).');
    }

    private function rescheduleLesson(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        $newLesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('new_lesson_id')));
        if ($booking === null || $lesson === null || $newLesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        $this->bus->dispatch(
            new RescheduleLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $newLesson->getId(),
                $actor->requireUser(),
                $args->string('reason'),
            ),
        );

        return ToolResult::success('Lekcja przełożona (admin).');
    }

    private function listPayments(ToolArguments $args): ToolResult
    {
        $search = $args->string('search') ?? '';
        $payments = $this->paymentRepository->findPendingWithSearch($search);
        $items = array_map($this->presenter->payment(...), $payments);

        return ToolResult::success(sprintf('Oczekujące płatności: %d.', count($items)), [
            'payments' => $items,
        ]);
    }

    private function listUnmatchedTransfers(): ToolResult
    {
        $transfers = $this->transferRepository->findBy([
            'payment' => null,
        ], [
            'transferredAt' => 'DESC',
        ]);
        $items = [];
        foreach ($transfers as $transfer) {
            $items[] = [
                'id' => $transfer->getId(),
                'sender' => $transfer->getSender(),
                'title' => $transfer->title,
                'amount' => $transfer->amount,
                'transferred_at' => $transfer->getTransferredAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return ToolResult::success(sprintf('Nieprzypisane przelewy: %d.', count($items)), [
            'transfers' => $items,
        ]);
    }

    private function assignTransfer(ToolArguments $args): ToolResult
    {
        $transfer = $this->transferRepository->find($args->requireInt('transfer_id'));
        $payment = $this->paymentRepository->find(Ulid::fromString($args->requireString('payment_id')));
        if ($transfer === null || $payment === null) {
            return ToolResult::failure('Transfer or payment not found');
        }
        $transfer->setPayment($payment);
        if ($this->paymentStateMachine->can($payment, Payment::TRANSITION_PAY)) {
            $this->paymentStateMachine->apply($payment, Payment::TRANSITION_PAY);
        }
        $this->entityManager->flush();

        return ToolResult::success('Przelew przypisany do płatności.', [
            'transfer_id' => $transfer->getId(),
            'payment' => $this->presenter->payment($payment),
        ]);
    }

    private function rejectTransfer(ToolArguments $args): ToolResult
    {
        $transfer = $this->transferRepository->find($args->requireInt('transfer_id'));
        if ($transfer === null) {
            return ToolResult::failure('Transfer not found');
        }
        $this->entityManager->remove($transfer);
        $this->entityManager->flush();

        return ToolResult::success('Przelew odrzucony/usunięty.');
    }

    private function triggerImport(): ToolResult
    {
        $this->bus->dispatch(new ImportTransfersFromMail());

        return ToolResult::success('Uruchomiono import przelewów z poczty.');
    }

    private function searchUsers(ToolArguments $args): ToolResult
    {
        $users = $this->userRepository->findAllMatching($args->requireString('query'));
        $items = array_map($this->presenter->userSummary(...), $users);

        return ToolResult::success(sprintf('Użytkownicy: %d.', count($items)), [
            'users' => $items,
        ]);
    }

    private function getUser(ToolArguments $args): ToolResult
    {
        $user = null;
        if ($args->has('user_id')) {
            $user = $this->userRepository->find($args->requireInt('user_id'));
        } elseif ($args->has('email')) {
            $user = $this->userRepository->findOneBy([
                'email' => strtolower($args->requireString('email')),
            ]);
        }
        if (!$user instanceof User) {
            return ToolResult::failure('User not found');
        }
        $data = $this->presenter->userSummary($user);
        $data['bookings'] = [];
        foreach ($user->getBookings() as $booking) {
            $data['bookings'][] = $this->presenter->booking($booking);
        }
        $data['children'] = [];
        foreach ($user->getChildren() as $child) {
            $data['children'][] = [
                'id' => (string) $child->getId(),
                'name' => $child->getName(),
                'birthday' => $child->getBirthday()?->format('Y-m-d'),
            ];
        }

        return ToolResult::success(
            sprintf('%s <%s>, rezerwacji: %d.', $user->getName(), $user->getEmail(), count($data['bookings'])),
            $data,
        );
    }

    private function listSeries(ToolArguments $args): ToolResult
    {
        $week = $args->string('week') ?? new \DateTimeImmutable('today')->format('Y-m-d');
        $start = new \DateTimeImmutable($week);
        $end = $start->modify('+7 days 23:59:59');
        $seriesList = $this->seriesRepository->findInRange($start, $end, $args->bool('include_cancelled'));
        $items = [];
        foreach ($seriesList as $series) {
            $first = $series->lessons->isEmpty() ? null : $series->getFirstLesson();
            $items[] = [
                'id' => (string) $series->getId(),
                'status' => $series->status,
                'type' => $series->type->value,
                'lessons_count' => $series->lessons->count(),
                'title' => $first?->getMetadata()->title,
                'ticket_options' => array_map($this->presenter->ticketOption(...), $series->ticketOptions),
            ];
        }

        return ToolResult::success(sprintf('Serie w tygodniu od %s: %d.', $week, count($items)), [
            'week' => $week,
            'series' => $items,
        ]);
    }

    private function updateSeries(ToolArguments $args): ToolResult
    {
        $series = $this->seriesRepository->find(Ulid::fromString($args->requireString('series_id')));
        if (!$series instanceof Series) {
            return ToolResult::failure('Series not found');
        }
        $ticketOptionsInput = $args->array('ticket_options');
        if ($ticketOptionsInput !== null) {
            $ticketOptions = [];
            foreach ($ticketOptionsInput as $row) {
                if (!is_array($row)) {
                    return ToolResult::failure('Each ticket_options item must be an object');
                }
                $typeRaw = $row['type'] ?? null;
                $amountRaw = $row['amount'] ?? '0.00';
                $currencyRaw = $row['currency'] ?? 'PLN';
                $descriptionRaw = $row['description'] ?? '';
                $policyRaw = $row['reschedule_policy'] ?? TicketReschedulePolicy::ONETIME_24H_BEFORE->value;
                if (!is_string($typeRaw) && !is_int($typeRaw)) {
                    return ToolResult::failure('ticket_options.type must be a string');
                }
                $type = TicketType::from((string) $typeRaw);
                $amount = is_string($amountRaw) || is_int($amountRaw) || is_float($amountRaw)
                    ? (string) $amountRaw
                    : '0.00';
                $currency = is_string($currencyRaw) ? $currencyRaw : 'PLN';
                $description = is_string($descriptionRaw) ? $descriptionRaw : '';
                $policyValue = is_string($policyRaw) ? $policyRaw : TicketReschedulePolicy::ONETIME_24H_BEFORE->value;
                $ticketOptions[] = new TicketOption(
                    $type,
                    Money::of($amount, $currency !== '' ? $currency : 'PLN'),
                    $description,
                    TicketReschedulePolicy::from($policyValue),
                );
            }
            $series->ticketOptions = $ticketOptions;
        }

        $this->entityManager->flush();

        return ToolResult::success(
            sprintf('Seria %s zaktualizowana (status: %s).', (string) $series->getId(), $series->status),
            [
                'id' => (string) $series->getId(),
                'status' => $series->status,
                'ticket_options' => array_map($this->presenter->ticketOption(...), $series->ticketOptions),
            ],
        );
    }

    private function cloneTemplateLesson(ToolArguments $args): ToolResult
    {
        $templateId = $args->requireString('template_lesson_id');
        if (!Ulid::isValid(trim($templateId))) {
            return ToolResult::failure(
                'Invalid template_lesson_id ULID. To list workshops use user.list_upcoming_lessons. To create a lesson use admin.clone_template_lesson with a real ULID.',
                'Niepoprawny ULID szablonu. Lista zajęć: user.list_upcoming_lessons. Tworzenie: admin.clone_template_lesson z prawdziwym ULID.',
            );
        }

        $template = $this->lessonRepository->find(Ulid::fromString($templateId));
        if ($template === null) {
            return ToolResult::failure('Template lesson not found');
        }
        $schedule = new \DateTimeImmutable($args->requireString('schedule'));
        $metadata = $template->getMetadata();
        $capacity = $args->int('capacity');
        if ($capacity !== null) {
            $metadata = $metadata->withCapacity($capacity);
        }
        $lesson = new Lesson($metadata, $schedule);
        $series = $template->getSeries();
        if ($series !== null) {
            $lesson->setSeries($series);
        }
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        return ToolResult::success(
            sprintf('Utworzono lekcję %s na %s.', $metadata->title, $schedule->format('Y-m-d H:i')),
            $this->presenter->lesson($lesson),
        );
    }

    private function notifyUser(ToolArguments $args): ToolResult
    {
        $user = null;
        if ($args->has('user_id')) {
            $user = $this->userRepository->find($args->requireInt('user_id'));
        } elseif ($args->has('email')) {
            $user = $this->userRepository->findOneBy([
                'email' => strtolower($args->requireString('email')),
            ]);
        }
        if (!$user instanceof User) {
            return ToolResult::failure('User not found (provide user_id or email)');
        }

        $severity = NotificationSeverity::Info;
        if ($args->has('severity')) {
            $severity = NotificationSeverity::from($args->requireString('severity'));
        }

        $notification = $this->inAppNotificationService->notify(
            $user,
            $args->requireString('title'),
            $args->string('body'),
            $args->string('url'),
            $severity,
        );

        return ToolResult::success(sprintf('Wysłano powiadomienie do %s.', $user->getEmail()), [
            'notification_id' => (string) $notification->getId(),
            'user_id' => $user->getId(),
        ]);
    }
}
