<?php

declare(strict_types=1);

namespace App;

use App\Infrastructure\DependencyInjection\KeepTwigProfilerExtensionsUnpooledPass;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Kernel\CoroutinesSupportingKernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements WarmableInterface
{
    use MicroKernelTrait, CoroutinesSupportingKernel {
        // Symfony 8.1's KernelTrait defines these too; the coroutine-safe versions must win.
        CoroutinesSupportingKernel::getContainerBaseClass insteadof MicroKernelTrait;
        CoroutinesSupportingKernel::initializeContainer insteadof MicroKernelTrait;
    }

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Must run before swoole-bundle's StatefulServicesPass (TYPE_BEFORE_REMOVING, -10000) reads the tags.
        $container->addCompilerPass(new KeepTwigProfilerExtensionsUnpooledPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }
}
