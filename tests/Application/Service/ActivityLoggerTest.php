<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\ActivityLogger;
use App\Entity\ActivityLog;
use App\Entity\ActivityType;
use App\Repository\ActivityLogRepository;
use App\Tests\Assembler\UserAssembler;
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
        self::assertInstanceOf(ActivityLogger::class, $logger);

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

        self::assertCount(1, $recent);
        self::assertSame(ActivityType::BOOKING_CREATED, $recent[0]->getType());
        self::assertSame('Ola Nowak zarezerwowała zajęcia', $recent[0]->getTitle());
        self::assertSame($user, $recent[0]->getSubject());
    }

    public function testDedupeKeySkipsASecondEntry(): void
    {
        $logger = self::getContainer()->get(ActivityLogger::class);
        self::assertInstanceOf(ActivityLogger::class, $logger);

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
        $matching = $em->getRepository(ActivityLog::class)->findBy(['dedupeKey' => 'transfer_unmatched:123']);

        self::assertCount(1, $matching);
        self::assertSame('Nierozpoznany przelew', $matching[0]->getTitle());
    }
}
