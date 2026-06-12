<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Http\Server;

interface SwooleServerProviderInterface
{
    public function getServer(): Server;
}
