<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Event\PreRunEvent;

#[Group('unit')]
final class SchedulerConnectionResetterTest extends TestCase
{
    public function testOnPreRunEnsuresConnection(): void
    {
        $connectionEnsurer = $this->createMock(ConnectionEnsurerInterface::class);
        $connectionEnsurer->expects($this->once())
            ->method('ensureConnection');

        $resetter = new SchedulerConnectionResetter($connectionEnsurer);
        $resetter->onPreRun($this->createMock(PreRunEvent::class));
    }
}
