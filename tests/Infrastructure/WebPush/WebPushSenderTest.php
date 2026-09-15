<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\WebPush;

use App\Application\Repository\PushSubscriptionRepositoryInterface;
use App\Infrastructure\WebPush\WebPushSender;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;

#[Group('unit')]
final class WebPushSenderTest extends TestCase
{
    public function testDoesNothingWhenVapidKeysAreNotConfigured(): void
    {
        $subscriptions = $this->createMock(PushSubscriptionRepositoryInterface::class);
        $subscriptions->expects($this->never())->method('findByUser');

        $sender = new WebPushSender(
            $this->createMock(ClientInterface::class),
            ['subject' => '', 'publicKey' => '', 'privateKey' => ''],
            $subscriptions,
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $sender->sendToUser(UserAssembler::new()->withId(1)->assemble(), 'Title', 'Body', '/panel');
    }

    public function testDoesNothingWhenUserHasNoSubscriptions(): void
    {
        $subscriptions = $this->createMock(PushSubscriptionRepositoryInterface::class);
        $subscriptions->method('findByUser')->willReturn([]);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $sender = new WebPushSender(
            $httpClient,
            ['subject' => 'mailto:test@example.com', 'publicKey' => 'public-key', 'privateKey' => 'private-key'],
            $subscriptions,
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $sender->sendToUser(UserAssembler::new()->withId(1)->assemble(), 'Title', 'Body', '/panel');
    }
}
