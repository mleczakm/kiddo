<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\MessengerConnectionResetter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

#[Group('unit')]
final class MessengerConnectionResetterTest extends TestCase
{
    public function testOnWorkerMessageReceivedEnsuresConnection(): void
    {
        $connectionEnsurer = $this->createMock(ConnectionEnsurerInterface::class);
        $connectionEnsurer->expects($this->once())
            ->method('ensureConnection');

        $resetter = new MessengerConnectionResetter($connectionEnsurer);
        $resetter->onWorkerMessageReceived(new WorkerMessageReceivedEvent(new Envelope(new \stdClass()), 'async'));
    }
}
