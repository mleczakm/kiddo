<?php

declare(strict_types=1);

namespace App;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements WarmableInterface
{
    use MicroKernelTrait, CoroutinesSupportingKernel {
        // Symfony 8.1's KernelTrait defines these too; the coroutine-safe versions must win.
        CoroutinesSupportingKernel::getContainerBaseClass insteadof MicroKernelTrait;
        CoroutinesSupportingKernel::initializeContainer insteadof MicroKernelTrait;
    }
}
