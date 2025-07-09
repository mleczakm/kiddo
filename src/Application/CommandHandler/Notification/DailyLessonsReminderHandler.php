<?php

declare(strict_types=1);

namespace App\Application\CommandHandler\Notification;

use App\Application\Command\Notification\DailyLessonsReminder;
use App\Application\Query\Lesson\TodayLessonsQuery;
use App\Entity\Lesson;
use App\Repository\UserRepository;
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
