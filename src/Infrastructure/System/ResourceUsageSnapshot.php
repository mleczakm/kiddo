<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

/**
 * Point-in-time view of this container's PID-namespace resource consumption, assembled from
 * /proc by {@see ProcResourceUsageProbe}.
 *
 * Deliberately namespace-wide (every process, not just the caller): the Swoole master,
 * manager, HTTP worker and both task workers each carry their own PHP heap and their own
 * pooled Doctrine/HTTP/IMAP connections, and the growth this exists to catch has
 * historically shown up in exactly one of them at a time (see config/packages/swoole.yaml).
 * `docker stats` reports 0 B on the production LXC host - there is no cgroup memory
 * accounting there - so summing /proc is the only way to see it.
 */
final readonly class ResourceUsageSnapshot
{
    public function __construct(
        public int $processCount,
        public int $totalRssKib,
        public int $maxProcessRssKib,
        public int $totalOpenFds,
        public int $maxProcessOpenFds,
        public int $fdSoftLimit,
        public int $tcpInUse,
        public int $tcpOrphan,
        public int $tcpTimeWait,
        public int $tcpAllocated,
    ) {}

    public function totalRssBytes(): int
    {
        return $this->totalRssKib * 1024;
    }

    public function maxProcessRssBytes(): int
    {
        return $this->maxProcessRssKib * 1024;
    }

    public function totalRssMib(): int
    {
        return $this->totalRssKib >> 10; // KiB -> MiB
    }

    public function maxProcessRssMib(): int
    {
        return $this->maxProcessRssKib >> 10; // KiB -> MiB
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'process_count' => $this->processCount,
            'total_rss_mib' => $this->totalRssMib(),
            'total_rss_kib' => $this->totalRssKib,
            'max_process_rss_mib' => $this->maxProcessRssMib(),
            'total_open_fds' => $this->totalOpenFds,
            'max_process_open_fds' => $this->maxProcessOpenFds,
            'fd_soft_limit' => $this->fdSoftLimit,
            'tcp_in_use' => $this->tcpInUse,
            'tcp_orphan' => $this->tcpOrphan,
            'tcp_time_wait' => $this->tcpTimeWait,
            'tcp_allocated' => $this->tcpAllocated,
        ];
    }
}
