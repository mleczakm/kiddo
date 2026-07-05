<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole;

use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;

/**
 * Test implementation of CurrentWorkerRestarterInterface to track restart calls
 */
final class TestCurrentWorkerRestarter implements CurrentWorkerRestarterInterface
{
    private int $restartCount = 0;

    public function restart(): void
    {
        $this->restartCount++;
    }

    public function getRestartCount(): int
    {
        return $this->restartCount;
    }

    public function resetRestartCount(): void
    {
        $this->restartCount = 0;
    }
}
