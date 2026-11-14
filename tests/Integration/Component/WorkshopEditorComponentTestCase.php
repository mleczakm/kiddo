<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\Lesson;
use App\Entity\Series;
use App\Entity\User;
use App\Entity\WorkshopType;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

abstract class WorkshopEditorComponentTestCase extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected KernelBrowser $client;

    protected EntityManagerInterface $em;

    protected NotificationRepository $notificationRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $notificationRepository = static::getContainer()->get(NotificationRepository::class);
        static::assertInstanceOf(NotificationRepository::class, $notificationRepository);
        $this->notificationRepository = $notificationRepository;
    }

    /**
     * @return array{admin: User, series: Series, current: Lesson, sibling: Lesson, past: Lesson, cancelled: Lesson}
     */
    protected function buildSeriesWithLessons(): array
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $series = SeriesAssembler::new()->withType(WorkshopType::WEEKLY)->assemble();
        $this->em->persist($series);

        $current = LessonAssembler::new()
            ->withTitle('Current Title')
            ->withSchedule(new \DateTimeImmutable('+3 days 10:00'))
            ->assemble();
        $current->setSeries($series);
        $this->em->persist($current);

        $sibling = LessonAssembler::new()
            ->withTitle('Sibling Title')
            ->withSchedule(new \DateTimeImmutable('+10 days 10:00'))
            ->assemble();
        $sibling->setSeries($series);
        $this->em->persist($sibling);

        $pastLesson = LessonAssembler::new()
            ->withTitle('Past Title')
            ->withSchedule(new \DateTimeImmutable('-5 days 10:00'))
            ->assemble();
        $pastLesson->setSeries($series);
        $this->em->persist($pastLesson);

        $cancelledLesson = LessonAssembler::new()
            ->withTitle('Cancelled Title')
            ->withStatus('cancelled')
            ->withSchedule(new \DateTimeImmutable('+7 days 10:00'))
            ->assemble();
        $cancelledLesson->setSeries($series);
        $this->em->persist($cancelledLesson);

        $this->em->flush();

        return [
            'admin' => $admin,
            'series' => $series,
            'current' => $current,
            'sibling' => $sibling,
            'past' => $pastLesson,
            'cancelled' => $cancelledLesson,
        ];
    }
}
