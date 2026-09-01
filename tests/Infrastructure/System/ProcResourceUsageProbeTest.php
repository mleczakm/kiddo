<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\System;

use App\Infrastructure\System\ProcResourceUsageProbe;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('unit')]
final class ProcResourceUsageProbeTest extends TestCase
{
    private string $procPath;

    private Filesystem $filesystem;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->procPath = sys_get_temp_dir() . '/proc-probe-' . bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->procPath);
    }

    public function testAggregatesRssFdsAndSocketsAcrossTheFixtureTree(): void
    {
        $this->writeProcess(1, rssKib: 100_000, fdCount: 22);
        $this->writeProcess(12, rssKib: 205_000, fdCount: 37);
        $this->writeProcess(9, rssKib: 24_000, fdCount: 15);
        $this->writeFile('net/sockstat', <<<'TXT'
            sockets: used 58
            TCP: inuse 41 orphan 2 tw 14 alloc 3191 mem 0
            UDP: inuse 1 mem 4929
            TXT);
        $this->writeFile('self/limits', <<<'TXT'
            Limit                     Soft Limit           Hard Limit           Units
            Max open files            1024                 524288               files
            TXT);

        $snapshot = new ProcResourceUsageProbe($this->procPath)->capture();

        static::assertSame(3, $snapshot->processCount);
        static::assertSame(329_000, $snapshot->totalRssKib);
        static::assertSame(205_000, $snapshot->maxProcessRssKib);
        static::assertSame(intdiv(329_000, 1024), $snapshot->totalRssMib());
        static::assertSame(329_000 * 1024, $snapshot->totalRssBytes());
        static::assertSame(205_000 * 1024, $snapshot->maxProcessRssBytes());
        static::assertSame(74, $snapshot->totalOpenFds);
        static::assertSame(37, $snapshot->maxProcessOpenFds);
        static::assertSame(1024, $snapshot->fdSoftLimit);
        static::assertSame(41, $snapshot->tcpInUse);
        static::assertSame(2, $snapshot->tcpOrphan);
        static::assertSame(14, $snapshot->tcpTimeWait);
        static::assertSame(3191, $snapshot->tcpAllocated);
    }

    public function testDegradesGracefullyWhenSockstatAndLimitsAreMissing(): void
    {
        $this->writeProcess(1, rssKib: 50_000, fdCount: 10);

        $snapshot = new ProcResourceUsageProbe($this->procPath)->capture();

        static::assertSame(1, $snapshot->processCount);
        static::assertSame(50_000, $snapshot->totalRssKib);
        static::assertSame(0, $snapshot->fdSoftLimit);
        static::assertSame(0, $snapshot->tcpInUse);
        static::assertSame(0, $snapshot->tcpAllocated);
    }

    public function testSkipsProcessDirectoriesThatVanishMidScan(): void
    {
        $this->writeProcess(1, rssKib: 50_000, fdCount: 10);
        $this->filesystem->mkdir($this->procPath . '/2'); // directory exists but no status file

        $snapshot = new ProcResourceUsageProbe($this->procPath)->capture();

        static::assertSame(1, $snapshot->processCount);
        static::assertSame(50_000, $snapshot->totalRssKib);
    }

    private function writeProcess(int $pid, int $rssKib, int $fdCount): void
    {
        $this->writeFile(
            sprintf('%d/status', $pid),
            sprintf("Name:\tphp\nState:\tS (sleeping)\nVmRSS:\t%d kB\nThreads:\t1\n", $rssKib),
        );

        for ($fd = 0; $fd < $fdCount; $fd++) {
            $this->writeFile(sprintf('%d/fd/%d', $pid, $fd), '');
        }
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $this->filesystem->dumpFile($this->procPath . '/' . $relativePath, $contents);
    }
}
