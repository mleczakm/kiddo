<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\NewBookingNotifier;
use App\Entity\FinanceContact;
use App\Entity\Notification;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class NewBookingNotifierTest extends KernelTestCase
{
    public function testNotifiesFinanceContactsAndConnectedLessonOrSeriesHostsOnly(): void
    {
        $customer = UserAssembler::new()->withEmail('customer@example.com')->assemble();
        $finance = UserAssembler::new()->withEmail('finance@example.com')->assemble();
        $lessonHost = UserAssembler::new()->withEmail('lesson-host@example.com')->withRoles('ROLE_HOST')->assemble();
        $seriesHost = UserAssembler::new()->withEmail('series-host@example.com')->withRoles('ROLE_HOST')->assemble();
        $unrelatedAdmin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();

        $series = SeriesAssembler::new()->assemble();
        $series->addInstructor($seriesHost);
        $lesson = LessonAssembler::new()->withTitle('Sensoryka')->withSeries($series)->assemble();
        $lesson->addInstructor($lessonHost);
        // A finance contact who also hosts this lesson still receives only one copy.
        $lesson->addInstructor($finance);
        $booking = BookingAssembler::new()->withUser($customer)->withLessons($lesson)->assemble();
        $lesson->addBooking($booking);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$customer, $finance, $lessonHost, $seriesHost, $unrelatedAdmin] as $user) {
            $em->persist($user);
        }
        $em->persist(new FinanceContact($finance));
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($booking);
        $em->flush();

        /** @var NewBookingNotifier $notifier */
        $notifier = self::getContainer()->get(NewBookingNotifier::class);
        $notifier->notify($booking);

        $notifications = $em->getRepository(Notification::class);
        static::assertCount(1, $notifications->findBy(['user' => $finance]));
        static::assertCount(1, $notifications->findBy(['user' => $lessonHost]));
        static::assertCount(1, $notifications->findBy(['user' => $seriesHost]));
        static::assertCount(0, $notifications->findBy(['user' => $unrelatedAdmin]));
        static::assertCount(0, $notifications->findBy(['user' => $customer]));
    }
}
