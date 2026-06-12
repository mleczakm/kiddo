<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Symfony;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class SchedulerTest extends KernelTestCase
{
    private Scheduler $scheduler;

    private ConnectionEnsurerInterface $connectionEnsurer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->scheduler = self::getContainer()->get(Scheduler::class);
        $this->connectionEnsurer = self::getContainer()->get(ConnectionEnsurerInterface::class);
    }

    public function testSchedulerHasConnectionEnsurer(): void
    {
        $this->assertInstanceOf(ConnectionEnsurerInterface::class, $this->connectionEnsurer);
    }

    public function testSchedulerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Scheduler::class, $this->scheduler);
    }

    public function testSchedulerRunDoesNotThrow(): void
    {
        // The scheduler should be able to run without throwing exceptions
        // even if no messages are due to be dispatched
        $this->expectNotToPerformAssertions();
        $this->scheduler->run();
    }
}
