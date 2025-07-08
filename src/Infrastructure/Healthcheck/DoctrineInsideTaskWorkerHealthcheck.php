<?php

declare(strict_types=1);

namespace App\Infrastructure\Healthcheck;

use App\Application\Command\DoctrineInsideTaskWorkerCheck;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use SymfonyHealthCheckBundle\Check\CheckInterface;
use SymfonyHealthCheckBundle\Dto\Response;

class DoctrineInsideTaskWorkerHealthcheck implements CheckInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private CacheInterface $cache,
    ) {}

    public function check(): Response
    {
        $this->cache->set($key = 'doctrine_inside_task_worker', false, 5);

        $this->bus->dispatch(new DoctrineInsideTaskWorkerCheck($key));

        $retry = 0;
        while (! $this->cache->get('doctrine_inside_task_worker')) {
            $retry++;

            if ($retry <= 5) {
                continue;
            }

            return new Response(
                'doctrine_inside_task_worker',
                false,
                'Doctrine inside task worker is not healthy',
                [
                    'retry' => $retry,
                ]
            );
        }

        $this->cache->delete('doctrine_inside_task_worker');

        return new Response('doctrine_inside_task_worker', true, 'Doctrine inside task worker is healthy');
    }
}
