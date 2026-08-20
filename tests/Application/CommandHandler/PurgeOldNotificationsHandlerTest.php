<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\PurgeOldNotifications;
use App\Application\CommandHandler\PurgeOldNotificationsHandler;
use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class PurgeOldNotificationsHandlerTest extends KernelTestCase
{
    public function testPurgesNotificationsOlderThanTwoMonths(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = new User('purge-handler@example.com', 'Purge');
        $em->persist($user);
        $em->flush();

        $createdAt = new \ReflectionProperty(Notification::class, 'createdAt');
        $old = new Notification($user, 'Old', null, null, NotificationSeverity::Info);
        $createdAt->setValue($old, new \DateTimeImmutable('2020-01-01 00:00:00'));
        $em->persist($old);

        $fresh = new Notification($user, 'Fresh', null, null, NotificationSeverity::Info);
        $createdAt->setValue($fresh, new \DateTimeImmutable('now'));
        $em->persist($fresh);
        $em->flush();

        $handler = self::getContainer()->get(PurgeOldNotificationsHandler::class);
        static::assertInstanceOf(PurgeOldNotificationsHandler::class, $handler);
        $handler(new PurgeOldNotifications());

        /** @var NotificationRepository $repo */
        $repo = self::getContainer()->get(NotificationRepository::class);
        $remaining = $repo->findRecentForUser($user, 10);
        static::assertCount(1, $remaining);
        static::assertSame('Fresh', $remaining[0]->getTitle());
    }
}
