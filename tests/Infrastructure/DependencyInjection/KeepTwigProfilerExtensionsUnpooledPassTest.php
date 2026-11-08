<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\DependencyInjection;

use App\Infrastructure\DependencyInjection\KeepTwigProfilerExtensionsUnpooledPass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[Group('unit')]
final class KeepTwigProfilerExtensionsUnpooledPassTest extends TestCase
{
    public function testItDropsTheResetTagOnlyFromTheProfilerExtensions(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.extension.webprofiler', new Definition(\stdClass::class)
            ->addTag('twig.extension')
            ->addTag('kernel.reset', ['method' => 'reset']));
        $container->setDefinition('other.service', new Definition(\stdClass::class)->addTag('kernel.reset', [
            'method' => 'reset',
        ]));

        new KeepTwigProfilerExtensionsUnpooledPass()->process($container);

        static::assertFalse($container->getDefinition('twig.extension.webprofiler')->hasTag('kernel.reset'));
        static::assertTrue($container->getDefinition('twig.extension.webprofiler')->hasTag('twig.extension'));
        static::assertTrue($container->getDefinition('other.service')->hasTag('kernel.reset'));
    }

    public function testItIsANoOpWhenTheProfilerIsNotInstalled(): void
    {
        $container = new ContainerBuilder();

        new KeepTwigProfilerExtensionsUnpooledPass()->process($container);

        static::assertFalse($container->hasDefinition('twig.extension.webprofiler'));
    }
}
