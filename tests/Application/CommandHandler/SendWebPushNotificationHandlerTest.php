<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\SendWebPushNotification;
use App\Application\CommandHandler\SendWebPushNotificationHandler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * WebPushSender is a concrete `final readonly` infra service (not an
 * interface), matching how InAppNotificationService is tested — through the
 * real container rather than a mock. VAPID keys are unset in the test env,
 * so WebPushSender no-ops without touching the network; see
 * WebPushSenderTest for that guard's own unit coverage.
 */
#[Group('functional')]
final class SendWebPushNotificationHandlerTest extends KernelTestCase
{
    public function testHandlesAnExistingUserWithoutError(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withEmail('web-push-handler@example.com')->assemble();
        $em->persist($user);
        $em->flush();

        $handler = self::getContainer()->get(SendWebPushNotificationHandler::class);
        static::assertInstanceOf(SendWebPushNotificationHandler::class, $handler);

        $userId = $user->getId();
        static::assertNotNull($userId);
        $handler->__invoke(new SendWebPushNotification($userId, 'Title', 'Body', '/panel'));

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenUserNoLongerExists(): void
    {
        self::bootKernel();
        $handler = self::getContainer()->get(SendWebPushNotificationHandler::class);
        static::assertInstanceOf(SendWebPushNotificationHandler::class, $handler);

        $handler->__invoke(new SendWebPushNotification(-1, 'Title'));

        $this->addToAssertionCount(1);
    }
}
