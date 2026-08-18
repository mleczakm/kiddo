<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Symfony;

use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class SchedulerTest extends KernelTestCase
{
    private Scheduler $scheduler;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->scheduler = self::getContainer()->get(Scheduler::class);
    }

    public function testSchedulerCanBeInstantiated(): void
    {
        static::assertInstanceOf(Scheduler::class, $this->scheduler);
    }

    public function testSchedulerRunDoesNotThrow(): void
    {
        // The scheduler should be able to run without throwing exceptions
        // even if no messages are due to be dispatched
        $this->expectNotToPerformAssertions();
        $this->scheduler->run();
    }
}
