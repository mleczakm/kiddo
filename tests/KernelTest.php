<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Kernel;

class KernelTest extends TestCase
{
    public function testKernelBoots(): void
    {
        $kernel = new Kernel('test', true);
        $this->assertInstanceOf(Kernel::class, $kernel);
    }
}
