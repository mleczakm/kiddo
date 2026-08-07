<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Query\Lesson\TodayLessonsQuery;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\LessonInstructorResolver;
use App\Entity\Lesson;
use App\Entity\NotificationSeverity;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use Brick\Money\Money;
use Ds\Map;
use Ds\Set;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsMessageHandler]
readonly class DailyLessonsReminderHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private LessonInstructorResolver $instructorResolver,
        private TodayLessonsQuery $todayLessonsQuery,
        private Environment $twig,
        private InAppNotificationService $inAppNotifications,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(DailyLessonsReminder $command): void
    {
        $date = $command->date;
        $lessons = $this->todayLessonsQuery->forDate($date);
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');

        $yesterday = $date->modify('-1 day');
        $yesterdayStart = $yesterday->setTime(0, 0, 0);
        $yesterdayEnd = $yesterday->setTime(23, 59, 59);

        $newUsers = $this->userRepository->findCreatedBetween($yesterdayStart, $yesterdayEnd);
        $newBookings = $this->bookingRepository->findCreatedBetween($yesterdayStart, $yesterdayEnd);
        $revenue = array_reduce(
            $this->paymentRepository->findPaidBetween($yesterdayStart, $yesterdayEnd),
            static fn(Money $carry, Payment $payment): Money => $carry->plus($payment->getAmount()),
            Money::zero('PLN'),
        );

        $content = $this->buildReport($lessons, $date, $yesterday, $newUsers, $newBookings, $revenue);
        $subject = $this->twig->render('email/notification/daily-schedule-subject.html.twig', [
            'lessons' => $lessons,
            'date' => $date,
        ]);

        $notification = new Notification()
            ->importance('')
            ->subject($subject)
            ->content($content);

        foreach ($admins as $admin) {
            $this->notifier->send($notification, new Recipient($admin->getEmail()));
        }

        $this->inAppNotifications->notifyAdmins(
            $this->translator->trans('notifications.in_app.daily_reminder.admin.title', [], 'messages'),
            $this->translator->trans('notifications.in_app.daily_reminder.admin.body', [
                'count' => count($lessons),
                'date' => $date->format('d.m.Y'),
            ], 'messages'),
            $this->urlGenerator->generate('app_admin_schedule'),
            NotificationSeverity::Info,
        );

        $adminIds = [];
        foreach ($admins as $admin) {
            $adminIds[] = $admin->getId();
        }

        /** @var Map<User, Set<Lesson>> $lessonsByInstructor */
        $lessonsByInstructor = new Map();
        foreach ($lessons as $lesson) {
            foreach ($this->instructorResolver->resolve([$lesson]) as $instructor) {
                if (in_array($instructor->getId(), $adminIds, true)) {
                    continue;
                }
                $instructorLessons = $lessonsByInstructor->get($instructor, new Set());
                $instructorLessons->add($lesson);
                $lessonsByInstructor->put($instructor, $instructorLessons);
            }
        }
        foreach ($lessonsByInstructor as $instructor => $instructorLessons) {
            $instructorContent = $this->twig->render('email/notification/daily-instructor-schedule.html.twig', [
                'lessons' => $instructorLessons,
                'date' => $date,
                'instructor' => $instructor,
            ]);
            $instructorSubject = $this->twig->render('email/notification/daily-instructor-schedule-subject.html.twig', [
                'date' => $date,
            ]);
            $instructorNotification = new Notification()
                ->importance('')
                ->subject($instructorSubject)
                ->content($instructorContent);
            $this->notifier->send($instructorNotification, new Recipient($instructor->getEmail()));
        }

        /** @var Map<User, Set<Lesson>> $usersWithLessons */
        $usersWithLessons = new Map();
        foreach ($lessons as $lesson) {
            foreach ($lesson->getBookings() as $booking) {
                $user = $booking->getUser();
                $userLessons = $usersWithLessons->get($user, new Set());
                $userLessons->add($lesson);
                $usersWithLessons->put($user, $userLessons);
            }
        }
        foreach ($usersWithLessons as $user => $userLessons) {
            $userContent = $this->twig->render('email/notification/daily-user-reminder.html.twig', [
                'lessons' => $userLessons,
                'date' => $date,
                'user' => $user,
            ]);
            $userSubject = $this->twig->render('email/notification/daily-user-reminder-subject.html.twig', [
                'date' => $date,
            ]);
            $userNotification = new Notification()
                ->importance('')
                ->subject($userSubject)
                ->content($userContent);
            $this->notifier->send($userNotification, new Recipient($user->getEmail()));

            $titles = [];
            foreach ($userLessons as $lesson) {
                $titles[] = $lesson->getMetadata()->title;
            }
            $this->inAppNotifications->notify(
                $user,
                $this->translator->trans('notifications.in_app.daily_reminder.user.title', [], 'messages'),
                $this->translator->trans('notifications.in_app.daily_reminder.user.body', [
                    'lessons' => implode(', ', $titles),
                    'date' => $date->format('d.m.Y'),
                ], 'messages'),
                $this->urlGenerator->generate('dashboard'),
                NotificationSeverity::Info,
            );
        }
    }

    /**
     * @param Lesson[] $lessons
     * @param User[] $newUsers
     * @param \App\Entity\Booking[] $newBookings
     */
    private function buildReport(
        array $lessons,
        \DateTimeImmutable $date,
        \DateTimeImmutable $yesterday,
        array $newUsers,
        array $newBookings,
        Money $revenue,
    ): string {
        return $this->twig->render('email/notification/daily-schedule.html.twig', [
            'lessons' => $lessons,
            'date' => $date,
            'yesterday' => $yesterday,
            'newUsers' => $newUsers,
            'newBookings' => $newBookings,
            'revenue' => $revenue,
        ]);
    }
}
