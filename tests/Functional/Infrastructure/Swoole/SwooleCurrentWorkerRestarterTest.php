<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Swoole;

use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
use App\Infrastructure\Swoole\SwooleCurrentWorkerRestarter;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class SwooleCurrentWorkerRestarterTest extends KernelTestCase
{
    private CurrentWorkerRestarterInterface $restarter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->restarter = self::getContainer()->get(CurrentWorkerRestarterInterface::class);
    }

    public function testRestarterIsInstanceOfSwooleCurrentWorkerRestarter(): void
    {
        $this->assertInstanceOf(SwooleCurrentWorkerRestarter::class, $this->restarter);
    }

    public function testRestarterImplementsInterface(): void
    {
        $this->assertInstanceOf(CurrentWorkerRestarterInterface::class, $this->restarter);
    }

    public function testRestartDoesNotThrowExceptionWhenNotInSwooleWorker(): void
    {
        // When not running in a Swoole worker (e.g. in tests), restart() should not throw
        $this->expectNotToPerformAssertions();
        $this->restarter->restart();
    }
}
