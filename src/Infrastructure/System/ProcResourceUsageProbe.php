<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds a {@see ResourceUsageSnapshot} straight from /proc. Only does file reads (no
 * shelling out, no syscalls beyond that), so it is cheap enough to call both from a health
 * check on the request path and from the scheduler tick.
 *
 * `$procPath` exists purely so tests can point at a fixture tree; production always reads
 * the real /proc, which - exactly like {@see \App\Infrastructure\Healthcheck\ProcessCountHealthcheck} -
 * is this container's own PID namespace, not the host's.
 */
final readonly class ProcResourceUsageProbe
{
    private Filesystem $filesystem;

    public function __construct(
        private string $procPath = '/proc',
    ) {
        $this->filesystem = new Filesystem();
    }

    public function capture(): ResourceUsageSnapshot
    {
        $processDirectories = glob($this->procPath . '/[0-9]*', GLOB_ONLYDIR);

        if ($processDirectories === false) {
            $processDirectories = [];
        }

        $processCount = 0;
        $totalRssKib = 0;
        $maxProcessRssKib = 0;
        $totalOpenFds = 0;
        $maxProcessOpenFds = 0;

        foreach ($processDirectories as $processDirectory) {
            $status = $this->read($processDirectory . '/status');

            if ($status === null) {
                // Process exited between the glob and this read - just skip it.
                continue;
            }

            $processCount++;

            $matches = [];

            if (preg_match('/^VmRSS:\s+(\d+)\s+kB/m', $status, $matches) === 1) {
                $rssKib = (int) $matches[1];
                $totalRssKib += $rssKib;
                $maxProcessRssKib = max($maxProcessRssKib, $rssKib);
            }

            $openFds = $this->countDirectoryEntries($processDirectory . '/fd');

            if ($openFds !== null) {
                $totalOpenFds += $openFds;
                $maxProcessOpenFds = max($maxProcessOpenFds, $openFds);
            }
        }

        [$tcpInUse, $tcpOrphan, $tcpTimeWait, $tcpAllocated] = $this->readTcpSocketStats();

        return new ResourceUsageSnapshot(
            processCount: $processCount,
            totalRssKib: $totalRssKib,
            maxProcessRssKib: $maxProcessRssKib,
            totalOpenFds: $totalOpenFds,
            maxProcessOpenFds: $maxProcessOpenFds,
            fdSoftLimit: $this->readFdSoftLimit(),
            tcpInUse: $tcpInUse,
            tcpOrphan: $tcpOrphan,
            tcpTimeWait: $tcpTimeWait,
            tcpAllocated: $tcpAllocated,
        );
    }

    /**
     * Parses the single `TCP:` line of /proc/net/sockstat, e.g.
     * `TCP: inuse 41 orphan 0 tw 14 alloc 3191 mem 0`. `alloc` climbing while `inuse` stays
     * flat is the signature of leaked sockets - the likely shape of "outgoing connections
     * (mail) silently stop working while the container still reports healthy".
     *
     * @return array{int, int, int, int} inuse, orphan, tw, alloc
     */
    private function readTcpSocketStats(): array
    {
        $sockstat = $this->read($this->procPath . '/net/sockstat');

        if ($sockstat === null) {
            return [0, 0, 0, 0];
        }

        $matches = [];

        if (
            preg_match('/^TCP:\s+inuse\s+(\d+)\s+orphan\s+(\d+)\s+tw\s+(\d+)\s+alloc\s+(\d+)/m', $sockstat, $matches)
            !== 1
        ) {
            return [0, 0, 0, 0];
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3], (int) $matches[4]];
    }

    /**
     * Soft RLIMIT_NOFILE from /proc/self/limits (`Max open files   <soft>   <hard>   files`).
     * The container is started without `--ulimit`, so this is the Docker daemon default
     * (1024 as of writing) - low enough that an fd leak hits it well before memory pressure.
     */
    private function readFdSoftLimit(): int
    {
        $limits = $this->read($this->procPath . '/self/limits');

        if ($limits === null) {
            return 0;
        }

        $matches = [];

        if (preg_match('/^Max open files\s+(\d+)/m', $limits, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function read(string $path): ?string
    {
        try {
            return $this->filesystem->readFile($path);
        } catch (IOException) {
            return null;
        }
    }

    private function countDirectoryEntries(string $path): ?int
    {
        try {
            return iterator_count(new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS));
        } catch (\UnexpectedValueException) {
            // Directory vanished with its process, or is not readable.
            return null;
        }
    }
}
