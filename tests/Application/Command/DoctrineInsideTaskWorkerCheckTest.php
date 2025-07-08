<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\DoctrineInsideTaskWorkerCheck;
use PHPUnit\Framework\TestCase;

class DoctrineInsideTaskWorkerCheckTest extends TestCase
{
    public function testConstructWithKey(): void
    {
        $key = 'asd';
        $command = new DoctrineInsideTaskWorkerCheck($key);
        $this->assertSame($key, $command->cacheKey);
    }
}
