<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\InAppNotificationService;
use App\Entity\NotificationSeverity;
use App\Repository\NotificationRepository;
use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class InAppNotificationServiceTest extends KernelTestCase
{
    public function testNotifyPersistsHtmlBodyUrlAndSeverity(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = UserAssembler::new()->withEmail('inapp-user@example.com')->assemble();
        $em->persist($user);
        $em->flush();

        $service = self::getContainer()->get(InAppNotificationService::class);
        static::assertInstanceOf(InAppNotificationService::class, $service);
        $notification = $service->notify(
            $user,
            'Hello',
            'Body with <strong>HTML</strong>',
            '/panel',
            NotificationSeverity::Success,
        );

        static::assertSame('Hello', $notification->getTitle());
        static::assertSame('Body with <strong>HTML</strong>', $notification->getBody());
        static::assertSame('/panel', $notification->getUrl());
        static::assertSame(NotificationSeverity::Success, $notification->getSeverity());
        static::assertTrue($notification->isUnread());

        /** @var NotificationRepository $repo */
        $repo = self::getContainer()->get(NotificationRepository::class);
        static::assertSame(1, $repo->countUnreadForUser($user));
    }

    public function testNotifyAdminsCreatesOnePerAdmin(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $admin1 = UserAssembler::new()->withEmail('inapp-admin1@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $admin2 = UserAssembler::new()->withEmail('inapp-admin2@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $user = UserAssembler::new()->withEmail('inapp-plain@example.com')->assemble();
        $em->persist($admin1);
        $em->persist($admin2);
        $em->persist($user);
        $em->flush();

        $service = self::getContainer()->get(InAppNotificationService::class);
        static::assertInstanceOf(InAppNotificationService::class, $service);
        $created = $service->notifyAdmins('Admin news', 'Details', '/admin', NotificationSeverity::Warning);

        static::assertCount(2, $created);

        /** @var NotificationRepository $repo */
        $repo = self::getContainer()->get(NotificationRepository::class);
        static::assertSame(1, $repo->countUnreadForUser($admin1));
        static::assertSame(1, $repo->countUnreadForUser($admin2));
        static::assertSame(0, $repo->countUnreadForUser($user));
    }
}
