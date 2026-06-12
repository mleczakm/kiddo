<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\HttpServer;

final readonly class HttpServerSwooleServerProvider implements SwooleServerProviderInterface
{
    public function __construct(
        private HttpServer $httpServer,
    ) {}

    public function getServer(): Server
    {
        return $this->httpServer->getServer();
    }
}
