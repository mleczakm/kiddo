<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use App\Infrastructure\Symfony\Form\CoroutineAwareTwigRendererEngine;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Swaps Symfony's Twig form engine for one that binds itself to the Environment of the running coroutine,
 * see {@see CoroutineAwareTwigRendererEngine} for why Twig 3.30 needs that under swoole-bundle.
 */
final class UseCoroutineAwareTwigFormEnginePass implements CompilerPassInterface
{
    private const string ENGINE_ID = 'twig.form.engine';

    /**
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::ENGINE_ID)) {
            return;
        }

        $definition = $container->getDefinition(self::ENGINE_ID);
        if ($definition->getClass() !== TwigRendererEngine::class) {
            return;
        }

        $definition->setClass(CoroutineAwareTwigRendererEngine::class);
    }
}
