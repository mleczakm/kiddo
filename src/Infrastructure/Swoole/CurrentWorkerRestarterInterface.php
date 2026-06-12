<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

interface CurrentWorkerRestarterInterface
{
    public function restart(): void;
}
