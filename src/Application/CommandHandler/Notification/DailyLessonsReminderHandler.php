<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Query\Lesson\TodayLessonsQuery;
use App\Entity\Lesson;
use App\Entity\User;
use App\Repository\UserRepository;
use Ds\Map;
use Ds\Set;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Twig\Environment;

#[AsMessageHandler]
readonly class DailyLessonsReminderHandler
{
    public function __construct(
        private NotifierInterface $notifier,
        private UserRepository $userRepository,
        private TodayLessonsQuery $todayLessonsQuery,
        private Environment $twig,
    ) {}

    public function __invoke(DailyLessonsReminder $command): void
    {
        $date = $command->date;
        $lessons = $this->todayLessonsQuery->forDate($date);
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');

        $content = $this->buildReport($lessons, $date);
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

        /** @var Map<User, Set<Lesson>> $usersWithLessons */
        $usersWithLessons = new \Ds\Map();
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
        }
    }

    /**
     * @param Lesson[] $lessons
     */
    private function buildReport(array $lessons, \DateTimeImmutable $date): string
    {
        return $this->twig->render('email/notification/daily-schedule.html.twig', [
            'lessons' => $lessons,
            'date' => $date,
        ]);
    }
}
