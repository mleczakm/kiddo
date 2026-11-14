<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Dev only: WebProfilerBundle tags `twig.extension.webprofiler` with `kernel.reset`, and swoole-bundle
 * proxifies every kernel.reset service into a coroutine pool. TwigProcessor deliberately makes the
 * profiler extensions non-shared instead (one per pooled Twig Environment), because compiled templates
 * read `$this->extensions[WebProfilerExtension::class]` and a pool proxy is keyed under its generated
 * class, so every render in debug mode dies with "Undefined array key ...WebProfilerExtension".
 * Dropping the tag keeps the extension out of the pool; a fresh instance per Environment needs no reset.
 */
final class KeepTwigProfilerExtensionsUnpooledPass implements CompilerPassInterface
{
    private const array EXTENSION_IDS = ['twig.extension.webprofiler', 'twig.extension.profiler'];

    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        foreach (self::EXTENSION_IDS as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $container->getDefinition($id)->clearTag('kernel.reset');
        }
    }
}
