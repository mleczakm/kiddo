<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Command\AddBooking;
use App\Application\Command\Notification\SendVerificationCode;
use App\Entity\Child;
use App\Entity\PaymentFactory;
use App\Entity\User;
use App\Message\CancelLessonBooking;
use App\Message\RefundLessonBooking;
use App\Message\RescheduleLessonBooking;
use App\Repository\BookingRepository;
use App\Repository\ChildRepository;
use App\Repository\LessonRepository;
use App\Repository\NotificationRepository;
use App\Repository\PaymentCodeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Ulid;

#[AutoconfigureTag('app.chat_tool_provider')]
final readonly class UserChatTools implements ChatToolProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private LessonRepository $lessonRepository,
        private BookingRepository $bookingRepository,
        private ChildRepository $childRepository,
        private NotificationRepository $notificationRepository,
        private PaymentCodeRepository $paymentCodeRepository,
        private LessonPresenter $presenter,
        private UserRepository $userRepository,
        private ChatTokenManager $tokenManager,
        #[Autowire(service: 'limiter.auth_email_limiter')]
        private RateLimiterFactory $authEmailRateLimiter,
        private CacheItemPoolInterface $cache,
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
                'user.me',
                'Return the logged-in parent profile (name, email, phone, children_count). Call instead of asking the user for personal data already stored on the account.',
                [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ),
            new ToolDefinition(
                'user.update_profile',
                'Update parent name, email and/or phone.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'name' => [
                            'type' => 'string',
                        ],
                        'email' => [
                            'type' => 'string',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Polish phone number',
                        ],
                    ],
                    'required' => ['confirm'],
                ],
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.list_children',
                'List children (id, name, birthday) on the parent account. Use before booking when assigning child_id; do not invent children or ask for names already returned here.',
                [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ),
            new ToolDefinition(
                'user.add_child',
                'Add a child to the parent account.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'name' => [
                            'type' => 'string',
                        ],
                        'birthday' => [
                            'type' => 'string',
                            'description' => 'YYYY-MM-DD optional',
                        ],
                    ],
                    'required' => ['confirm', 'name'],
                ],
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.delete_child',
                'Delete a child from the parent account.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'child_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'child_id'],
                ],
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.list_upcoming_lessons',
                'List available workshops/lessons for browsing or booking. Optional filters: age (years), week (Monday YYYY-MM-DD), query, limit. Public — works for guests.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional free-text filter (title/theme)',
                        ],
                        'age' => [
                            'type' => 'integer',
                            'description' => 'Child age in years, e.g. 2. Omit for any age.',
                        ],
                        'week' => [
                            'type' => 'string',
                            'description' => 'Week start Monday YYYY-MM-DD; defaults to today',
                        ],
                        'limit' => [
                            'type' => 'integer',
                        ],
                    ],
                ],
                requiresAuth: false,
            ),
            new ToolDefinition(
                'user.get_lesson',
                'Get one workshop/lesson details, seats and ticket options. Pass lesson_id from user.list_upcoming_lessons.',
                [
                    'type' => 'object',
                    'properties' => [
                        'lesson_id' => [
                            'type' => 'string',
                            'description' => 'ULID of the lesson',
                        ],
                    ],
                    'required' => ['lesson_id'],
                ],
                requiresAuth: false,
            ),
            new ToolDefinition(
                'user.create_booking',
                'Book a workshop for the logged-in parent. Uses account data from the chat token — do NOT ask for name/email/phone. Pass lesson_id + ticket_type (one_time|carnet_4) + confirm=true. Optional child_id from user.list_children. Returns BLIK payment instructions (phone, amount, title code, ~24h validity). Guests must log in first.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'lesson_id' => [
                            'type' => 'string',
                            'description' => 'ULID from user.list_upcoming_lessons / user.get_lesson',
                        ],
                        'ticket_type' => [
                            'type' => 'string',
                            'enum' => ['one_time', 'carnet_4'],
                        ],
                        'child_id' => [
                            'type' => 'string',
                            'description' => 'Optional ULID from user.list_children; omit if not needed',
                        ],
                    ],
                    'required' => ['confirm', 'lesson_id', 'ticket_type'],
                ],
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.get_payment_instructions',
                'BLIK-to-phone payment details for a pending booking: phone number, amount, payment title code, validity (~24h), optional bank account. Prefer booking_id or payment_code from user.create_booking. Logged-in parent only.',
                [
                    'type' => 'object',
                    'properties' => [
                        'booking_id' => [
                            'type' => 'string',
                        ],
                        'payment_code' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ),
            new ToolDefinition('user.list_bookings', 'List parent bookings, optionally filtered by status.', [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'enum' => ['pending', 'active', 'cancelled', 'past'],
                    ],
                ],
            ]),
            new ToolDefinition('user.get_booking', 'Get one booking owned by the parent.', [
                'type' => 'object',
                'properties' => [
                    'booking_id' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['booking_id'],
            ]),
            new ToolDefinition('user.list_carnets', 'List multi-lesson (carnet) bookings for the parent.', [
                'type' => 'object',
                'properties' => new \stdClass(),
            ]),
            new ToolDefinition(
                'user.booking_reschedule_options',
                'List alternative dates when rescheduling an EXISTING booking. Requires real booking_id and lesson_id ULIDs. Not for browsing the workshop catalog — use user.list_upcoming_lessons instead.',
                [
                    'type' => 'object',
                    'properties' => [
                        'booking_id' => [
                            'type' => 'string',
                            'description' => 'ULID of an existing booking',
                        ],
                        'lesson_id' => [
                            'type' => 'string',
                            'description' => 'ULID of the lesson being moved',
                        ],
                    ],
                    'required' => ['booking_id', 'lesson_id'],
                ],
            ),
            new ToolDefinition(
                'user.reschedule_lesson',
                'Reschedule a booked lesson to another lesson in the series.',
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
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.cancel_lesson',
                'Cancel a lesson on a booking without refund.',
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
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.request_refund',
                'Request a refund for a paid lesson on a booking.',
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
                requiresConfirm: true,
            ),
            new ToolDefinition('user.list_notifications', 'List recent in-app notifications for the parent.', [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                    ],
                ],
            ]),
            new ToolDefinition('user.mark_notification_read', 'Mark a notification as read.', [
                'type' => 'object',
                'properties' => [
                    'notification_id' => [
                        'type' => 'string',
                    ],
                ],
                'required' => ['notification_id'],
            ]),
            new ToolDefinition(
                'user.delete_notification',
                'Soft-delete a notification.',
                [
                    'type' => 'object',
                    'properties' => [
                        ...$confirm,
                        'notification_id' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['confirm', 'notification_id'],
                ],
                requiresConfirm: true,
            ),
            new ToolDefinition(
                'user.register',
                'Register a new user account. A 6-digit verification code is emailed to the user; ask them to read it back, then call user.login_with_code.',
                [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'User email address',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'User full name',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Polish phone number (optional)',
                        ],
                    ],
                    'required' => ['email', 'name'],
                ],
                requiresAuth: false,
            ),
            new ToolDefinition(
                'user.request_login_code',
                'Request a login verification code via email. Rate limited: 3 per hour per email.',
                [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'User email address',
                        ],
                    ],
                    'required' => ['email'],
                ],
                requiresAuth: false,
            ),
            new ToolDefinition(
                'user.login_with_code',
                'Authenticate with email and verification code to get chat token.',
                [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'User email address',
                        ],
                        'code' => [
                            'type' => 'string',
                            'description' => '6-digit verification code',
                        ],
                    ],
                    'required' => ['email', 'code'],
                ],
                requiresAuth: false,
            ),
        ];
    }

    #[\Override]
    public function supports(string $name): bool
    {
        return str_starts_with($name, 'user.');
    }

    #[\Override]
    public function call(string $name, ChatActor $actor, array $arguments): ToolResult
    {
        try {
            $args = new ToolArguments($arguments);

            return match ($name) {
                'user.me' => $this->me($actor),
                'user.update_profile' => $this->updateProfile($actor, $args),
                'user.list_children' => $this->listChildren($actor),
                'user.add_child' => $this->addChild($actor, $args),
                'user.delete_child' => $this->deleteChild($actor, $args),
                'user.list_upcoming_lessons' => $this->listUpcomingLessons($args),
                'user.get_lesson' => $this->getLesson($args),
                'user.create_booking' => $this->createBooking($actor, $args),
                'user.get_payment_instructions' => $this->getPaymentInstructions($actor, $args),
                'user.list_bookings' => $this->listBookings($actor, $args),
                'user.get_booking' => $this->getBooking($actor, $args),
                'user.list_carnets' => $this->listCarnets($actor),
                'user.booking_reschedule_options' => $this->listRescheduleTargets($actor, $args),
                'user.reschedule_lesson' => $this->rescheduleLesson($actor, $args),
                'user.cancel_lesson' => $this->cancelLesson($actor, $args),
                'user.request_refund' => $this->requestRefund($actor, $args),
                'user.list_notifications' => $this->listNotifications($actor, $args),
                'user.mark_notification_read' => $this->markNotificationRead($actor, $args),
                'user.delete_notification' => $this->deleteNotification($actor, $args),
                'user.register' => $this->registerUser($args),
                'user.request_login_code' => $this->requestLoginCode($args),
                'user.login_with_code' => $this->loginWithCode($args),
                default => ToolResult::failure(sprintf('Unknown user tool: %s', $name)),
            };
        } catch (\InvalidArgumentException $e) {
            return ToolResult::failure($e->getMessage());
        }
    }

    private function me(ChatActor $actor): ToolResult
    {
        $data = $this->presenter->userSummary($actor->requireUser());

        return ToolResult::success(sprintf('Profil: %s (%s)', $data['name'], $data['email']), $data);
    }

    private function updateProfile(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $user = $actor->requireUser();
        if ($args->has('name')) {
            $user->setName($args->requireString('name'));
        }
        if ($args->has('email')) {
            $user->setEmail($args->requireString('email'));
        }
        if ($args->has('phone')) {
            try {
                $phone = PhoneNumberUtil::getInstance()->parse($args->requireString('phone'), 'PL');
                $user->setPhone($phone);
            } catch (NumberParseException $e) {
                return ToolResult::failure('Invalid phone number: ' . $e->getMessage());
            }
        }
        $this->entityManager->flush();

        return ToolResult::success('Profil zaktualizowany.', $this->presenter->userSummary($user));
    }

    private function listChildren(ChatActor $actor): ToolResult
    {
        $children = [];
        foreach ($actor->requireUser()->getChildren() as $child) {
            $children[] = [
                'id' => (string) $child->getId(),
                'name' => $child->getName(),
                'birthday' => $child->getBirthday()?->format('Y-m-d'),
                'age_years' => $child->getAgeYears(),
            ];
        }

        return ToolResult::success(sprintf('Masz %d dzieci na koncie.', count($children)), [
            'children' => $children,
        ]);
    }

    private function addChild(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $name = trim($args->string('name') ?? '');
        if ($name === '') {
            return ToolResult::failure('Child name is required');
        }
        $birthday = null;
        if ($args->has('birthday')) {
            $birthday = new \DateTimeImmutable($args->requireString('birthday'));
        }
        $child = new Child($actor->requireUser(), $name, $birthday);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        return ToolResult::success(sprintf('Dodano dziecko %s.', $name), [
            'child_id' => (string) $child->getId(),
            'name' => $name,
        ]);
    }

    private function deleteChild(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $child = $this->childRepository->find(Ulid::fromString($args->requireString('child_id')));
        if (!$child instanceof Child || $child->getOwner()->getId() !== $actor->userId()) {
            return ToolResult::failure('Child not found');
        }
        $name = $child->getName();
        $this->entityManager->remove($child);
        $this->entityManager->flush();

        return ToolResult::success(sprintf('Usunięto dziecko %s.', $name));
    }

    private function listUpcomingLessons(ToolArguments $args): ToolResult
    {
        $week = $args->string('week') ?? new \DateTimeImmutable('today')->format('Y-m-d');
        $query = $args->string('query');
        $age = $args->int('age');
        $limit = $args->int('limit', 20) ?? 20;

        $lessons = $this->lessonRepository->findByFilters($query, $age, $week, $limit);
        $items = array_map($this->presenter->lesson(...), $lessons);

        return ToolResult::success(sprintf('Znaleziono %d zajęć (tydzień od %s).', count($items), $week), [
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

        return ToolResult::success(
            sprintf(
                '%s — %s, wolne miejsca: %d',
                $data['title'],
                new \DateTimeImmutable($data['schedule'])->format('d.m.Y H:i'),
                $data['available_spots'],
            ),
            $data,
        );
    }

    private function createBooking(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $lessonId = $args->requireString('lesson_id');
        $ticketType = $args->requireString('ticket_type');
        $childId = $args->string('child_id');

        $lesson = $this->lessonRepository->find(Ulid::fromString($lessonId));
        if ($lesson === null) {
            return ToolResult::failure('Lesson not found');
        }
        if ($lesson->getAvailableSpots() <= 0) {
            return ToolResult::failure('No available spots on this lesson');
        }

        $paymentCode = new PaymentFactory()->generateCode();
        $this->bus->dispatch(new AddBooking(
            userId: $actor->userId(),
            lessonId: $lessonId,
            ticketType: $ticketType,
            childId: $childId,
            paymentCode: $paymentCode,
        ));

        $codeEntity = $this->paymentCodeRepository->findOneBy([
            'code' => strtoupper($paymentCode),
        ]);
        $payment = $codeEntity?->getPayment();
        $booking = $payment !== null
            ? $this->bookingRepository->findOneBy([
                'payment' => $payment,
            ])
            : null;
        if ($payment !== null) {
            $instructions = $this->presenter->paymentInstructions($payment);
            $summary = $instructions['instruction_pl'];
        } else {
            $instructions = [
                'payment_code' => $paymentCode,
            ];
            $summary = sprintf(
                'Rezerwacja utworzona. Opłać przelewem BLIK z tytułem zawierającym kod %s.',
                $paymentCode,
            );
        }

        return ToolResult::success($summary, [
            'payment_code' => $paymentCode,
            'booking_id' => $booking !== null ? (string) $booking->getId() : null,
            'lesson_id' => $lessonId,
            'ticket_type' => $ticketType,
            'payment' => $instructions,
        ]);
    }

    private function getPaymentInstructions(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $payment = null;
        if ($args->has('booking_id')) {
            $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
            if ($booking === null || $booking->getUser()->getId() !== $actor->userId()) {
                return ToolResult::failure('Booking not found');
            }
            $payment = $booking->getPayment();
        } elseif ($args->has('payment_code')) {
            $code = $this->paymentCodeRepository->findOneBy([
                'code' => strtoupper($args->requireString('payment_code')),
            ]);
            $payment = $code?->getPayment();
            if ($payment !== null && $payment->getUser()->getId() !== $actor->userId()) {
                return ToolResult::failure('Payment not found');
            }
        }

        if ($payment === null) {
            return ToolResult::failure('Provide booking_id or payment_code');
        }

        $data = $this->presenter->paymentInstructions($payment);

        return ToolResult::success($data['instruction_pl'], $data);
    }

    private function listBookings(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $status = $args->string('status');
        $bookings = [];
        foreach ($actor->requireUser()->getBookings() as $booking) {
            if ($status !== null && $booking->getStatus() !== $status) {
                continue;
            }
            $bookings[] = $this->presenter->booking($booking);
        }

        return ToolResult::success(sprintf('Znaleziono %d rezerwacji.', count($bookings)), [
            'bookings' => $bookings,
        ]);
    }

    private function getBooking(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        if ($booking === null || $booking->getUser()->getId() !== $actor->userId()) {
            return ToolResult::failure('Booking not found');
        }
        $data = $this->presenter->booking($booking);

        return ToolResult::success(sprintf('Rezerwacja %s, status %s.', $data['id'], $data['status']), $data);
    }

    private function listCarnets(ChatActor $actor): ToolResult
    {
        $carnets = [];
        foreach ($actor->requireUser()->getBookings() as $booking) {
            if (!$booking->isCarnet()) {
                continue;
            }
            $carnets[] = $this->presenter->booking($booking);
        }

        return ToolResult::success(sprintf('Karnety: %d.', count($carnets)), [
            'carnets' => $carnets,
        ]);
    }

    private function listRescheduleTargets(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $bookingId = $args->requireString('booking_id');
        $lessonId = $args->requireString('lesson_id');
        if (!$this->looksLikeUlid($bookingId) || !$this->looksLikeUlid($lessonId)) {
            return ToolResult::failure(
                'Invalid booking_id/lesson_id. This tool is only for rescheduling an existing booking. To list available workshops call user.list_upcoming_lessons.',
                'To nie jest przegląd oferty. Do listy zajęć użyj user.list_upcoming_lessons. Ten tool wymaga prawdziwych ULID rezerwacji i lekcji.',
            );
        }

        $booking = $this->bookingRepository->find(Ulid::fromString($bookingId));
        $lesson = $this->lessonRepository->find(Ulid::fromString($lessonId));
        if ($booking === null || $lesson === null || $booking->getUser()->getId() !== $actor->userId()) {
            return ToolResult::failure('Booking or lesson not found');
        }
        $series = $lesson->getSeries();
        if ($series === null) {
            return ToolResult::failure('Lesson has no series — cannot reschedule');
        }

        $available = $this->lessonRepository->findAvailableLessonsForReschedule($series, $lesson->schedule);
        $targets = [];
        foreach ($available as $candidate) {
            if ($candidate->getId()->equals($lesson->getId())) {
                continue;
            }
            if ($booking->getLessons()->contains($candidate)) {
                continue;
            }
            if ($candidate->getAvailableSpots() <= 0) {
                continue;
            }
            $targets[] = $this->presenter->lesson($candidate);
        }

        return ToolResult::success(sprintf('Dostępnych terminów do przełożenia: %d.', count($targets)), [
            'targets' => $targets,
        ]);
    }

    private function looksLikeUlid(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !Ulid::isValid($trimmed)) {
            return false;
        }

        $blocked = ['any', 'all', 'none', 'null', 'undefined', 'n/a', '*'];

        return !in_array(strtolower($trimmed), $blocked, true);
    }

    private function rescheduleLesson(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        $newLesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('new_lesson_id')));
        if ($booking === null || $lesson === null || $newLesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        if ($booking->getUser()->getId() !== $actor->userId() && !$actor->isAdmin()) {
            return ToolResult::failure('Booking not found');
        }
        if (!$booking->canRescheduleLesson($lesson) && !$actor->isAdmin()) {
            return ToolResult::failure('Reschedule is not allowed for this booking/lesson');
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

        return ToolResult::success('Lekcja została przełożona.');
    }

    private function cancelLesson(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($booking === null || $lesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        if ($booking->getUser()->getId() !== $actor->userId() && !$actor->isAdmin()) {
            return ToolResult::failure('Booking not found');
        }

        $this->bus->dispatch(
            new CancelLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $actor->requireUser(),
                $args->string('reason'),
            ),
        );

        return ToolResult::success('Lekcja została odwołana z rezerwacji.');
    }

    private function requestRefund(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $booking = $this->bookingRepository->find(Ulid::fromString($args->requireString('booking_id')));
        $lesson = $this->lessonRepository->find(Ulid::fromString($args->requireString('lesson_id')));
        if ($booking === null || $lesson === null) {
            return ToolResult::failure('Booking or lesson not found');
        }
        if ($booking->getUser()->getId() !== $actor->userId() && !$actor->isAdmin()) {
            return ToolResult::failure('Booking not found');
        }
        if (!$booking->canRequestRefundForLesson($lesson) && !$actor->isAdmin()) {
            return ToolResult::failure('Refund is not available within 24h of the lesson');
        }

        $this->bus->dispatch(
            new RefundLessonBooking(
                $booking->getId(),
                $lesson->getId(),
                $actor->requireUser(),
                $args->string('reason'),
            ),
        );

        return ToolResult::success('Wysłano prośbę o zwrot.');
    }

    private function listNotifications(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $limit = $args->int('limit', 20) ?? 20;
        $notifications = $this->notificationRepository->findRecentForUser($actor->requireUser(), $limit);
        $items = [];
        foreach ($notifications as $notification) {
            $items[] = [
                'id' => (string) $notification->getId(),
                'title' => $notification->getTitle(),
                'body' => $notification->getBody(),
                'url' => $notification->getUrl(),
                'unread' => $notification->isUnread(),
                'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ];
        }

        return ToolResult::success(
            sprintf(
                'Powiadomienia: %d (nieprzeczytane: %d).',
                count($items),
                $this->notificationRepository->countUnreadForUser($actor->requireUser()),
            ),
            [
                'notifications' => $items,
            ],
        );
    }

    private function markNotificationRead(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $notification = $this->notificationRepository->find(Ulid::fromString($args->requireString('notification_id')));
        if ($notification === null || $notification->getUser()->getId() !== $actor->userId()) {
            return ToolResult::failure('Notification not found');
        }
        $notification->markRead();
        $this->entityManager->flush();

        return ToolResult::success('Powiadomienie oznaczone jako przeczytane.');
    }

    private function deleteNotification(ChatActor $actor, ToolArguments $args): ToolResult
    {
        $notification = $this->notificationRepository->find(Ulid::fromString($args->requireString('notification_id')));
        if ($notification === null || $notification->getUser()->getId() !== $actor->userId()) {
            return ToolResult::failure('Notification not found');
        }
        $notification->softDelete();
        $this->entityManager->flush();

        return ToolResult::success('Powiadomienie usunięte.');
    }

    private function registerUser(ToolArguments $args): ToolResult
    {
        $email = $args->requireString('email');
        $name = $args->requireString('name');
        $phone = $args->string('phone');

        if ($this->userRepository->findOneBy([
            'email' => $email,
        ])) {
            return ToolResult::failure('Email already registered');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles(['ROLE_USER']);

        if ($phone !== null && $phone !== '') {
            try {
                $phoneObj = PhoneNumberUtil::getInstance()->parse($phone, 'PL');
                $user->setPhone($phoneObj);
            } catch (NumberParseException) {
                return ToolResult::failure('Invalid phone number');
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->issueVerificationCode($user->getEmail());

        return ToolResult::success('Registration successful. A verification code has been sent to the user\'s email. Ask them to read it back to you.', [
            'user_id' => $user->getId(),
        ]);
    }

    private function requestLoginCode(ToolArguments $args): ToolResult
    {
        $email = $args->requireString('email');

        // Rate limit per email (3 codes per hour)
        $limiter = $this->authEmailRateLimiter->create($email);
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();

            return ToolResult::failure(sprintf('Too many code requests. Please try again in %d seconds.', $retryAfter));
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if (!$user) {
            return ToolResult::failure('User not found');
        }

        $this->issueVerificationCode($user->getEmail());

        return ToolResult::success('Verification code sent to the user\'s email.');
    }

    private function loginWithCode(ToolArguments $args): ToolResult
    {
        $email = $args->requireString('email');
        $code = $args->requireString('code');

        $cacheKey = $this->verificationCodeCacheKey($email);
        $item = $this->cache->getItem($cacheKey);
        $storedCode = $item->get();

        if (!$item->isHit() || !is_string($storedCode) || !hash_equals($storedCode, $code)) {
            return ToolResult::failure('Invalid or expired code');
        }

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);
        if (!$user) {
            return ToolResult::failure('User not found');
        }

        $this->cache->deleteItem($cacheKey);

        if ($user->getConfirmedAt() === null) {
            $user->setConfirmedAt(Clock::get()->now());
        }
        $user->setLastLoginAt(Clock::get()->now());
        $this->entityManager->flush();

        $chatToken = $this->tokenManager->mint($user);

        return ToolResult::success('Login successful', [
            'chat_token' => $chatToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
        ]);
    }

    private function issueVerificationCode(string $email): void
    {
        $code = $this->generateVerificationCode();
        $item = $this->cache->getItem($this->verificationCodeCacheKey($email));
        $item->set($code);
        $item->expiresAfter(600);
        $this->cache->save($item);

        $this->bus->dispatch(new SendVerificationCode($email, $code));
    }

    private function verificationCodeCacheKey(string $email): string
    {
        // PSR-6 keys forbid {}()/\@: — hash the (lowercased) address instead of sanitizing it.
        return sprintf('verification_code_%s', hash('xxh3', mb_strtolower($email)));
    }

    private function generateVerificationCode(): string
    {
        return sprintf('%06d', random_int(0, 999_999));
    }
}
