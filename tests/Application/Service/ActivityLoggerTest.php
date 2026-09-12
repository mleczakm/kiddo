<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\ActivityLogger;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Entity\Notification;
use App\Infrastructure\Doctrine\Repository\ActivityLogRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ActivityLoggerTest extends KernelTestCase
{
    public function testLogPersistsAnActivityLogRow(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = UserAssembler::new()->withName('Ola Nowak')->assemble();
        $em->persist($user);
        $em->flush();

        $logger = self::getContainer()->get(ActivityLogger::class);
        static::assertInstanceOf(ActivityLogger::class, $logger);

        $logger->log(
            type: ActivityType::BOOKING_CREATED,
            title: 'Ola Nowak zarezerwowała zajęcia',
            subject: $user,
            summary: 'Sensoplastyka',
            url: '/admin/uzytkownicy/' . $user->getId(),
        );

        /** @var ActivityLogRepository $repo */
        $repo = self::getContainer()->get(ActivityLogRepository::class);
        $recent = $repo->findRecent(10);

        static::assertCount(1, $recent);
        static::assertSame(ActivityType::BOOKING_CREATED, $recent[0]->getType());
        static::assertSame('Ola Nowak zarezerwowała zajęcia', $recent[0]->getTitle());
        static::assertSame($user, $recent[0]->getSubject());
    }

    public function testDedupeKeySkipsASecondEntry(): void
    {
        $logger = self::getContainer()->get(ActivityLogger::class);
        static::assertInstanceOf(ActivityLogger::class, $logger);

        $logger->log(
            type: ActivityType::TRANSFER_UNMATCHED,
            title: 'Nierozpoznany przelew',
            dedupeKey: 'transfer_unmatched:123',
        );
        $logger->log(
            type: ActivityType::TRANSFER_UNMATCHED,
            title: 'Nierozpoznany przelew (ponowne sprawdzenie)',
            dedupeKey: 'transfer_unmatched:123',
        );

        /** @var ActivityLogRepository $repo */
        $repo = self::getContainer()->get(ActivityLogRepository::class);
        $em = self::getContainer()->get('doctrine')->getManager();
        $matching = $em->getRepository(ActivityLog::class)->findBy([
            'dedupeKey' => 'transfer_unmatched:123',
        ]);

        static::assertCount(1, $matching);
        static::assertSame('Nierozpoznany przelew', $matching[0]->getTitle());
    }

    /**
     * Regression test for WARSZTATOWNIA-BS/BP: ActivityLogSubscriber's flush()
     * covers the whole unit of work, not just the ActivityLog row it just
     * persisted. If something else pending in the same unit of work (here: a
     * Notification referencing a never-persisted User, exactly like
     * Payment#refundRequestedBy in production) makes Doctrine refuse the
     * flush, that must not propagate and roll back the real operation that
     * triggered the activity log entry — it should be logged and swallowed.
     */
    public function testFailedFlushFromAnUnrelatedDanglingEntityDoesNotPropagate(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = UserAssembler::new()->withName('Ola Nowak')->assemble();
        $em->persist($user);
        $em->flush();

        // A Notification referencing a User that was never persisted: pending
        // in the same unit of work, but not something ActivityLogger/the
        // subscriber has any say over.
        $unmanagedUser = UserAssembler::new()->withName('Nieznany')->assemble();
        $em->persist(new Notification($unmanagedUser, 'Test'));

        $logger = self::getContainer()->get(ActivityLogger::class);
        static::assertInstanceOf(ActivityLogger::class, $logger);

        $logger->log(type: ActivityType::BOOKING_CREATED, title: 'Ola Nowak zarezerwowała zajęcia', subject: $user);

        /** @var ActivityLogRepository $repo */
        $repo = self::getContainer()->get(ActivityLogRepository::class);
        // The flush failed, so the activity log entry itself is lost too —
        // that's the accepted trade-off. What matters is that log() above
        // didn't throw.
        static::assertCount(0, $repo->findRecent(10));
    }
}
