<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Swoole;

use App\Infrastructure\Swoole\ExceptionLoggingTaskHandler;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class ExceptionLoggingTaskHandlerTest extends KernelTestCase
{
    /**
     * Swoole\Server\Task has no public constructor and can't be instantiated outside
     * Swoole's own task dispatch, so handle() can't be exercised directly in a test - the
     * previous WorkerRestartingTaskHandlerTest had the same limitation. This just confirms
     * the container builds the decorator with its current, smaller dependency set (logger +
     * inner handler only) now that CurrentWorkerRestarterInterface has been removed entirely.
     */
    public function testHandlerBuildsWithoutAWorkerRestarterDependency(): void
    {
        self::bootKernel();

        $handler = self::getContainer()->get(ExceptionLoggingTaskHandler::class);

        static::assertInstanceOf(ExceptionLoggingTaskHandler::class, $handler);
    }
}
