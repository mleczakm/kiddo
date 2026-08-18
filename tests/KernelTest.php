<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class KernelTest extends TestCase
{
    public function testKernelBoots(): void
    {
        $kernel = new Kernel('test', true);
        static::assertInstanceOf(Kernel::class, $kernel);
    }
}
