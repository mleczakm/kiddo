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
        private readonly MessageBusInterface $bus,
        private readonly CacheInterface $cache,
    ) {}

    public function check(): Response
    {
        $retry = 0;
        try {
            $this->cache->delete('doctrine_inside_task_worker');

            $this->bus->dispatch(new DoctrineInsideTaskWorkerCheck('doctrine_inside_task_worker'));

            while (($result = $this->cache->get('doctrine_inside_task_worker')) === null) {
                $retry++;

                if ($retry <= 5) {
                    usleep(100000); // 100ms
                    continue;
                }

                return new Response(
                    'doctrine_inside_task_worker',
                    false,
                    'Doctrine inside task worker is not healthy (timeout)',
                    [
                        'retry' => $retry,
                    ]
                );
            }

            if (! $result) {
                return new Response(
                    'doctrine_inside_task_worker',
                    false,
                    'Doctrine inside task worker is not healthy (handler reported failure)',
                    [
                        'retry' => $retry,
                    ]
                );
            }

            $this->cache->delete('doctrine_inside_task_worker');
        } catch (\Throwable $e) {
            return new Response(
                'doctrine_inside_task_worker',
                false,
                'Doctrine inside task worker check failed: ' . $e->getMessage(),
                [
                    'retry' => $retry,
                ]
            );
        }

        return new Response('doctrine_inside_task_worker', true, 'Doctrine inside task worker is healthy');
    }
}
