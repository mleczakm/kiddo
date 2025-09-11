<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use PHPUnit\Framework\Attributes\Group;
use SymfonyHealthCheckBundle\Check\DoctrineORMCheck;
use App\Application\Command\DoctrineInsideTaskWorkerCheck;
use App\Application\CommandHandler\DoctrineInsideTaskWorkerCheckHandler;
use Kodus\Cache\MockCache;
use PHPUnit\Framework\TestCase;
use SymfonyHealthCheckBundle\Dto\Response;

#[Group('unit')]
class DoctrineInsideTaskWorkerCheckHandlerTest extends TestCase
{
    public function testFailOnNativeCheckFail(): void
    {
        $nullCache = new MockCache();
        $doctrineOrmCheck = $this->createMock(DoctrineORMCheck::class);
        $doctrineOrmCheck->expects($this->once())
            ->method('check')
            ->willReturn(new Response('', false, ''));
        $doctrineInsideTaskWorkerHandler = new DoctrineInsideTaskWorkerCheckHandler($nullCache, $doctrineOrmCheck);

        $doctrineInsideTaskWorkerHandler->__invoke(
            new DoctrineInsideTaskWorkerCheck('doctrine_inside_task_worker')
        );

        self::assertFalse($nullCache->get('doctrine_inside_task_worker'));
    }

    public function testFailOnNativeCheckFatal(): void
    {
        $nullCache = new MockCache();
        $doctrineOrmCheck = $this->createMock(DoctrineORMCheck::class);
        $doctrineOrmCheck->expects($this->once())
            ->method('check')
            ->willThrowException(new \LogicException());
        $doctrineInsideTaskWorkerHandler = new DoctrineInsideTaskWorkerCheckHandler($nullCache, $doctrineOrmCheck);

        $doctrineInsideTaskWorkerHandler->__invoke(
            new DoctrineInsideTaskWorkerCheck('doctrine_inside_task_worker')
        );

        self::assertFalse($nullCache->get('doctrine_inside_task_worker'));
    }

    public function testPassOnNativeCheckSuccess(): void
    {
        $nullCache = new MockCache();
        $doctrineOrmCheck = $this->createMock(DoctrineORMCheck::class);
        $doctrineOrmCheck->expects($this->once())
            ->method('check')
            ->willReturn(new Response('', true, ''));
        $doctrineInsideTaskWorkerHandler = new DoctrineInsideTaskWorkerCheckHandler($nullCache, $doctrineOrmCheck);

        $doctrineInsideTaskWorkerHandler->__invoke(
            new DoctrineInsideTaskWorkerCheck('doctrine_inside_task_worker')
        );

        self::assertTrue($nullCache->get('doctrine_inside_task_worker'));
    }
}
