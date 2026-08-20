<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Twig;

use App\Application\Templating\TemplateRendererInterface;
use Twig\Environment;

readonly class SymfonyTemplateRenderer implements TemplateRendererInterface
{
    public function __construct(
        private Environment $twig,
    ) {}

    /**
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\RuntimeError
     */
    #[\Override]
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
