<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\AgeRange;
use App\Entity\Booking;
use App\Entity\Child;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\Series;
use App\Entity\TicketOption;
use App\Entity\TicketReschedulePolicy;
use App\Entity\TicketType;
use App\Entity\Transfer;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Repository\BookingRepository;
use App\Repository\ChildRepository;
use App\Repository\LessonRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:dev:seed-demo',
    description: 'Creates a comprehensive, repeatable local dataset for previewing Kiddo admin screens.',
)]
final class SeedDemoDataCommand extends Command
{
    private const string DEMO_DOMAIN = 'demo.kiddo.local';

    private const string DEMO_TITLE_PREFIX = '[DEMO] ';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ChildRepository $childRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly LessonRepository $lessonRepository,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'replace',
            null,
            InputOption::VALUE_NONE,
            'Remove the previous demo dataset and recreate it. Non-demo records are never removed.',
        );
        $this->addOption(
            'purge',
            null,
            InputOption::VALUE_NONE,
            'Remove the demo dataset without creating a new one. Non-demo records are never removed.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (! in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('Demo data can only be seeded in the dev or test environment.');

            return Command::FAILURE;
        }

        if ($input->getOption('replace') && $input->getOption('purge')) {
            $io->error('Use either --replace or --purge, not both.');

            return Command::INVALID;
        }

        if ($input->getOption('purge')) {
            $this->removePreviousDemoData();
            $io->success('Demo dataset removed. Non-demo data was left untouched.');

            return Command::SUCCESS;
        }

        $existingAdmin = $this->userRepository->findOneBy([
            'email' => $this->demoEmail('admin'),
        ]);
        if ($existingAdmin instanceof User && ! $input->getOption('replace')) {
            $io->note('Demo data already exists. Run with --replace to recreate it.');
            $this->printAccessDetails($io);

            return Command::SUCCESS;
        }

        if ($input->getOption('replace')) {
            $this->removePreviousDemoData();
            $io->writeln('Previous demo dataset removed.');
        }

        $now = new \DateTimeImmutable('today 10:00');
        [$admin, $host, $parents, $children] = $this->createUsers($now);
        [$weeklyLessons, $oneTimeLessons] = $this->createLessons($now, $host);
        $this->entityManager->flush();

        $bookings = $this->createBookings($now, $admin, $parents, $children, $weeklyLessons, $oneTimeLessons);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Demo dataset created: %d users, %d children, %d lessons and %d bookings.',
            2 + count($parents),
            count($children),
            count($weeklyLessons) + count($oneTimeLessons),
            count($bookings),
        ));
        $this->printAccessDetails($io);

        return Command::SUCCESS;
    }

    /**
     * @return array{User, User, list<User>, list<Child>}
     */
    private function createUsers(\DateTimeImmutable $now): array
    {
        $admin = $this->newUser('admin', 'Alicja Administrator', ['ROLE_ADMIN'], '+48 500 100 100');
        $host = $this->newUser('host', 'Henryk Prowadzący', ['ROLE_HOST'], '+48 500 200 200');
        $parents = [
            $this->newUser('anna', 'Anna Kowalska', ['ROLE_USER'], '+48 501 111 111'),
            $this->newUser('bartek', 'Bartosz Nowak', ['ROLE_USER'], '+48 502 222 222'),
            $this->newUser('celina', 'Celina Wiśniewska', ['ROLE_USER'], '+48 503 333 333'),
            $this->newUser('damian', 'Damian Zieliński', ['ROLE_USER'], '+48 504 444 444'),
        ];

        foreach ([$admin, $host, ...$parents] as $user) {
            $user->setConfirmedAt($now->modify('-6 months'));
            $this->entityManager->persist($user);
        }

        $children = [
            new Child($parents[0], 'Zosia Kowalska', $now->modify('-3 years -4 months')),
            new Child($parents[0], 'Jan Kowalski', $now->modify('-6 years -2 months')),
            new Child($parents[1], 'Maja Nowak', $now->modify('-2 years -8 months')),
            new Child($parents[2], 'Leon Wiśniewski', $now->modify('-5 years -1 month')),
            new Child($parents[3], 'Pola Zielińska', $now->modify('-1 year -10 months')),
        ];
        foreach ($children as $child) {
            $this->entityManager->persist($child);
        }

        return [$admin, $host, $parents, $children];
    }

    /**
     * @return array{list<Lesson>, list<Lesson>}
     */
    private function createLessons(\DateTimeImmutable $now, User $host): array
    {
        $weeklySeries = new Series(
            new ArrayCollection(),
            WorkshopType::WEEKLY,
            [
                new TicketOption(TicketType::ONE_TIME, Money::of(
                    '79',
                    'PLN'
                ), 'Pojedyncze wejście', TicketReschedulePolicy::ONETIME_24H_BEFORE),
                new TicketOption(TicketType::CARNET_4, Money::of(
                    '279',
                    'PLN'
                ), 'Karnet na cztery spotkania', TicketReschedulePolicy::UNLIMITED_24H_BEFORE),
            ],
        );
        $weeklySeries->addInstructor($host);
        $this->entityManager->persist($weeklySeries);

        $weeklyLessons = [];
        foreach ([-7, 2, 9, 16, 23, 30] as $offset) {
            $lesson = $this->newLesson(
                '[DEMO] Sensoplastyka — kolory lata',
                $now->modify(sprintf('%+d days', $offset)),
                10,
                new AgeRange(2, 6),
                'Sensoryka',
                '#fef3c7',
            );
            $lesson->setSeries($weeklySeries)
                ->addInstructor($host);
            $this->entityManager->persist($lesson);
            $weeklyLessons[] = $lesson;
        }

        $oneTimeSeries = new Series(
            new ArrayCollection(),
            WorkshopType::ONE_TIME,
            [
                new TicketOption(TicketType::ONE_TIME, Money::of(
                    '95',
                    'PLN'
                ), 'Warsztat jednorazowy', TicketReschedulePolicy::ONETIME_24H_BEFORE),
            ],
        );
        $oneTimeSeries->addInstructor($host);
        $this->entityManager->persist($oneTimeSeries);

        $oneTimeLessons = [
            $this->newLesson(
                '[DEMO] Bobasowe muzykowanie',
                $now->modify('+3 days 2 hours'),
                8,
                new AgeRange(1, 3),
                'Muzyka',
                '#dbeafe'
            ),
            $this->newLesson(
                '[DEMO] Mali odkrywcy',
                $now->modify('+5 days 4 hours'),
                12,
                new AgeRange(4, 8),
                'Eksperymenty',
                '#dcfce7'
            ),
            $this->newLesson(
                '[DEMO] Rodzinne lepienie z gliny',
                $now->modify('+12 days 1 hour'),
                10,
                new AgeRange(3, 9),
                'Plastyka',
                '#fae8ff'
            ),
            $this->newLesson(
                '[DEMO] Odwołane warsztaty pokazowe',
                $now->modify('+7 days 3 hours'),
                10,
                new AgeRange(2, 7),
                'Pokazowe',
                '#fee2e2'
            ),
        ];
        $oneTimeLessons[3]->status = 'cancelled';
        foreach ($oneTimeLessons as $lesson) {
            $lesson->setSeries($oneTimeSeries)
                ->addInstructor($host);
            $this->entityManager->persist($lesson);
        }

        return [$weeklyLessons, $oneTimeLessons];
    }

    /**
     * @param list<User> $parents
     * @param list<Child> $children
     * @param list<Lesson> $weekly
     * @param list<Lesson> $oneTime
     *
     * @return list<Booking>
     */
    private function createBookings(
        \DateTimeImmutable $now,
        User $admin,
        array $parents,
        array $children,
        array $weekly,
        array $oneTime,
    ): array {
        $paidOnline = $this->payment($parents[0], '79', PaymentMethod::ONLINE, Payment::STATUS_PAID);
        $transfer = new Transfer(
            '46 2490 0005 0000 4000 1897 5420',
            'Anna Kowalska',
            'DEMO Zosia Sensoplastyka',
            '79.00',
            $now->modify('-4 days 3 hours')
        );
        $paidOnline->addTransfer($transfer);
        $this->entityManager->persist($transfer);
        $singlePaid = $this->booking(
            $parents[0],
            $children[0],
            $paidOnline,
            Booking::STATUS_ACTIVE,
            $now->modify('-5 days'),
            'Alergia na orzechy — proszę uważać na materiały.',
            $weekly[1]
        );

        $carnetPayment = $this->payment($parents[1], '279', PaymentMethod::ONLINE, Payment::STATUS_PAID);
        $carnet = $this->booking(
            $parents[1],
            $children[2],
            $carnetPayment,
            Booking::STATUS_ACTIVE,
            $now->modify('-12 days'),
            'Karnet 4 wejścia; Maja potrzebuje chwili na adaptację.',
            $weekly[1],
            $weekly[2],
            $weekly[3],
            $weekly[4]
        );

        $pendingPayment = $this->payment($parents[2], '95', PaymentMethod::ONLINE, Payment::STATUS_PENDING);
        $pending = $this->booking(
            $parents[2],
            $children[3],
            $pendingPayment,
            Booking::STATUS_PENDING,
            $now->modify('-2 hours'),
            null,
            $oneTime[0]
        );

        $onSitePayment = $this->payment($parents[3], '95', PaymentMethod::PAY_ON_PLACE, Payment::STATUS_PENDING);
        $waitingApproval = $this->booking(
            $parents[3],
            $children[4],
            $onSitePayment,
            Booking::STATUS_WAITING_APPROVAL,
            $now->modify('-1 day'),
            'Płatność kartą na miejscu.',
            $oneTime[1]
        );

        $cancelledPayment = $this->payment($parents[0], '95', PaymentMethod::ONLINE, Payment::STATUS_PAID);
        $cancelled = $this->booking(
            $parents[0],
            $children[1],
            $cancelledPayment,
            Booking::STATUS_ACTIVE,
            $now->modify('-10 days'),
            'Anulowano z powodu choroby dziecka.',
            $oneTime[1]
        );
        $cancelled->cancel($admin, 'Choroba dziecka');

        $refundedPayment = $this->payment($parents[2], '95', PaymentMethod::ONLINE, Payment::STATUS_REFUNDED);
        $refunded = $this->booking(
            $parents[2],
            $children[3],
            $refundedPayment,
            Booking::STATUS_ACTIVE,
            $now->modify('-14 days'),
            'Zwrot wykonany na rachunek klienta.',
            $oneTime[2]
        );
        $refunded->cancel($admin, 'Anulowanie ze zwrotem');

        $rescheduledPayment = $this->payment($parents[3], '79', PaymentMethod::ONLINE, Payment::STATUS_PAID);
        $rescheduled = $this->booking(
            $parents[3],
            $children[4],
            $rescheduledPayment,
            Booking::STATUS_ACTIVE,
            $now->modify('-8 days'),
            'Termin przełożony na prośbę rodzica.',
            $weekly[1]
        );
        $rescheduled->rescheduleLesson($weekly[1], $weekly[2], $admin);

        $pastPayment = $this->payment($parents[0], '79', PaymentMethod::ONLINE, Payment::STATUS_PAID);
        $past = $this->booking(
            $parents[0],
            $children[0],
            $pastPayment,
            Booking::STATUS_PAST,
            $now->modify('-20 days'),
            'Uczestnik obecny; zajęcia zakończone.',
            $weekly[0]
        );

        $unpaidWithoutPayment = $this->booking(
            $parents[1],
            $children[2],
            null,
            Booking::STATUS_ACTIVE,
            $now->modify('-1 day 4 hours'),
            'Rezerwacja ręczna — do rozliczenia.',
            $oneTime[2]
        );

        return [
            $singlePaid,
            $carnet,
            $pending,
            $waitingApproval,
            $cancelled,
            $refunded,
            $rescheduled,
            $past,
            $unpaidWithoutPayment,
        ];
    }

    /**
     * @param list<string> $roles
     */
    private function newUser(string $mailbox, string $name, array $roles, string $phone): User
    {
        $user = new User($this->demoEmail($mailbox), $name);
        $user->setRoles($roles);
        $user->setPhone(PhoneNumberUtil::getInstance()->parse($phone, 'PL'));

        return $user;
    }

    private function newLesson(
        string $title,
        \DateTimeImmutable $schedule,
        int $capacity,
        AgeRange $ageRange,
        string $category,
        string $theme,
    ): Lesson {
        return new Lesson(new LessonMetadata(
            $title,
            'Rozwojowe zajęcia demonstracyjne dla dzieci i rodziców.',
            $theme,
            'Kompletny opis przykładowych zajęć. Dane utworzone przez komendę developerską.',
            $capacity,
            $schedule,
            60,
            $ageRange,
            $category,
        ));
    }

    private function payment(User $user, string $amount, PaymentMethod $method, string $status): Payment
    {
        $payment = new Payment($user, Money::of($amount, 'PLN'), $method);
        $payment->setStatus($status);
        $this->entityManager->persist($payment);

        return $payment;
    }

    private function booking(
        User $user,
        ?Child $child,
        ?Payment $payment,
        string $status,
        \DateTimeImmutable $createdAt,
        ?string $note,
        Lesson ...$lessons,
    ): Booking {
        $booking = new Booking($user, $payment, ...$lessons);
        $booking->setChild($child)
            ->setStatus($status)
            ->setCreatedAt($createdAt)
            ->setNotes($note);
        $this->entityManager->persist($booking);

        return $booking;
    }

    private function removePreviousDemoData(): void
    {
        /** @var list<User> $users */
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.email LIKE :domain')
            ->setParameter('domain', '%@' . self::DEMO_DOMAIN)
            ->getQuery()
            ->getResult();
        if ($users !== []) {
            foreach ($this->bookingRepository->findBy([
                'user' => $users,
            ]) as $booking) {
                $this->entityManager->remove($booking);
            }
            $this->entityManager->flush();

            foreach ($this->paymentRepository->findBy([
                'user' => $users,
            ]) as $payment) {
                foreach ($payment->getTransfers() as $transfer) {
                    $transfer->setPayment(null);
                    $this->entityManager->remove($transfer);
                }
                $this->entityManager->remove($payment);
            }
            foreach ($users as $user) {
                foreach ($this->childRepository->findByOwner($user) as $child) {
                    $this->entityManager->remove($child);
                }
            }
            $this->entityManager->flush();
        }

        /** @var list<Lesson> $lessons */
        $lessons = $this->lessonRepository->createQueryBuilder('l')
            ->where('l.metadata.title LIKE :prefix')
            ->setParameter('prefix', self::DEMO_TITLE_PREFIX . '%')
            ->getQuery()
            ->getResult();
        /** @var array<string, Series> $series */
        $series = [];
        foreach ($lessons as $lesson) {
            if ($lesson->getSeries() instanceof Series) {
                $series[$lesson->getSeries()->getId()->toString()] = $lesson->getSeries();
            }
            $this->entityManager->remove($lesson);
        }
        $this->entityManager->flush();
        foreach ($series as $demoSeries) {
            $this->entityManager->remove($demoSeries);
        }
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    private function printAccessDetails(SymfonyStyle $io): void
    {
        $io->section('Demo accounts (passwordless login link)');
        $io->table(['Role', 'Email'], [
            ['Administrator', $this->demoEmail('admin')],
            ['Instructor / host', $this->demoEmail('host')],
            ['Parent', $this->demoEmail('anna')],
        ]);
        $io->writeln('Open /login and retrieve the generated login link from Mailpit: http://localhost:8025');
    }

    private function demoEmail(string $mailbox): string
    {
        return $mailbox . '@' . self::DEMO_DOMAIN;
    }
}
