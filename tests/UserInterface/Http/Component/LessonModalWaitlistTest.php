<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Application\Repository\WaitlistEntryRepositoryInterface;
use App\Entity\Booking;
use App\Entity\WorkshopType;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The full-lesson "join waitlist" affordance in LessonModal. The `waitlist`
 * flag is on in the test env (see novaway_feature_flag.yaml).
 */
#[Group('functional')]
final class LessonModalWaitlistTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testFullLessonLetsALoggedInUserJoinAndLeaveTheWaitlist(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(1)->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $lesson->setSeries($series);
        $holder = UserAssembler::new()->assemble();
        $user = UserAssembler::new()->withPhone('501222333')->assemble();
        $booking = BookingAssembler::new()
            ->withUser($holder)
            ->withStatus(Booking::STATUS_ACTIVE)
            ->withLessons($lesson)
            ->assemble();
        $lesson->addBooking($booking);
        foreach ([$series, $lesson, $holder, $user, $booking] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        static::assertSame(0, $lesson->getAvailableSpots());

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'modalOpened' => true],
            client: $client,
        );

        /** @var LessonModal $modal */
        $modal = $component->component();
        static::assertTrue($modal->isWaitlistOffered());
        static::assertFalse($modal->isOnWaitlist());

        $waitlist = static::getContainer()->get(WaitlistEntryRepositoryInterface::class);
        \assert($waitlist instanceof WaitlistEntryRepositoryInterface);

        $component->call('joinWaitlist');
        static::assertSame('lesson.waitlist.joined', $component->component()->waitlistNotice);
        static::assertCount(1, $waitlist->findActiveForUser($user));

        $component->call('leaveWaitlist');
        static::assertSame('lesson.waitlist.left', $component->component()->waitlistNotice);
        static::assertCount(0, $waitlist->findActiveForUser($user));
    }

    public function testWaitlistIsNotOfferedWhenSeatsAreAvailable(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withCapacity(5)->assemble())
            ->withSchedule(new \DateTimeImmutable('+10 days'))
            ->assemble();
        $lesson->setSeries($series);
        $user = UserAssembler::new()->withPhone('501222444')->assemble();
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'modalOpened' => true],
            client: $client,
        );

        /** @var LessonModal $modal */
        $modal = $component->component();
        static::assertFalse($modal->isWaitlistOffered());
    }
}
