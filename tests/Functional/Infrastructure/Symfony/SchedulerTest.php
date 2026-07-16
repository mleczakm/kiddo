<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Symfony;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class SchedulerTest extends KernelTestCase
{
    private Scheduler $scheduler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->scheduler = self::getContainer()->get(Scheduler::class);
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

    public function testSchedulerHasConnectionResetter(): void
    {
        $container = self::getContainer();
        $this->assertInstanceOf(
            SchedulerConnectionResetter::class,
            $container->get(SchedulerConnectionResetter::class)
        );
        $this->assertInstanceOf(
            ConnectionEnsurerInterface::class,
            $container->get(ConnectionEnsurerInterface::class)
        );
    }
}
