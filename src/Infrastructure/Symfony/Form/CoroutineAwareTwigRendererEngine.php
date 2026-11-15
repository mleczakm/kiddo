<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Form;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormView;
use Twig\Environment;

/**
 * Keeps the form engine talking to the Twig Environment of the coroutine it is rendering for.
 *
 * Under swoole-bundle both `twig` and `twig.form.engine` are pooled. The engine is constructed with the
 * `twig` proxy and keeps it, so it hands the proxy to `Twig\BlockChain`. Since Twig 3.30 a chain refuses
 * templates its Environment does not own, and the templates it loads are owned by the real Environment
 * behind the proxy - never by the proxy - so every form render dies with "A block chain cannot contain
 * templates from different Twig environments".
 *
 * Before each use the engine therefore swaps in the real Environment the proxy currently stands for. A
 * pooled engine can move to another coroutine, and so to another Environment, between two uses; the one
 * cached chain that outlives Symfony's reset() is dropped whenever that happens.
 */
final class CoroutineAwareTwigRendererEngine extends TwigRendererEngine
{
    private ?Environment $boundEnvironment = null;

    /**
     * @param array<mixed> $defaultThemes
     */
    public function __construct(
        array $defaultThemes,
        private readonly Environment $environmentOrProxy,
    ) {
        parent::__construct($defaultThemes, $environmentOrProxy);
    }

    /**
     * @throws \LogicException
     */
    #[\Override]
    public function setTheme(FormView $view, mixed $themes, bool $useDefaultThemes = true): void
    {
        $this->bindEnvironment();

        parent::setTheme($view, $themes, $useDefaultThemes);
    }

    /**
     * @throws \LogicException
     */
    #[\Override]
    public function getResourceForBlockName(FormView $view, string $blockName): mixed
    {
        $this->bindEnvironment();

        return parent::getResourceForBlockName($view, $blockName);
    }

    /**
     * @throws \LogicException
     */
    #[\Override]
    public function getResourceForBlockNameHierarchy(
        FormView $view,
        array $blockNameHierarchy,
        int $hierarchyLevel,
    ): mixed {
        $this->bindEnvironment();

        return parent::getResourceForBlockNameHierarchy($view, $blockNameHierarchy, $hierarchyLevel);
    }

    /**
     * @throws \LogicException
     */
    #[\Override]
    public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
    {
        $this->bindEnvironment();

        return parent::renderBlock($view, $resource, $blockName, $variables);
    }

    /**
     * @throws \LogicException
     */
    private function bindEnvironment(): void
    {
        $environment = $this->currentEnvironment();
        if ($environment === $this->boundEnvironment) {
            return;
        }

        $this->boundEnvironment = $environment;
        $this->replaceParentEnvironment($environment);
    }

    /**
     * @throws \LogicException
     */
    private function currentEnvironment(): Environment
    {
        if (!$this->environmentOrProxy instanceof ContextualProxy) {
            return $this->environmentOrProxy;
        }

        $environment = $this->environmentOrProxy->getContextualObject();
        if (!$environment instanceof Environment) {
            throw new \LogicException('The pooled Twig service did not resolve to a Twig environment.');
        }

        return $environment;
    }

    private function replaceParentEnvironment(Environment $environment): void
    {
        \Closure::bind(
            function (Environment $environment): void {
                $this->environment = $environment;
                $this->defaultChain = null;
            },
            $this,
            TwigRendererEngine::class,
        )($environment);
    }
}
