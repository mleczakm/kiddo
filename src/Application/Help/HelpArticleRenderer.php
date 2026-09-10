<?php

declare(strict_types=1);

namespace App\Application\Help;

use App\Application\Templating\TemplateRendererInterface;

/**
 * Renders a help article's Twig body and flattens it to plain text — the form
 * the chat assistant consumes. Panel links resolve through {@code path()} while
 * the template renders, so the plain text keeps real URLs.
 */
final readonly class HelpArticleRenderer
{
    public function __construct(
        private TemplateRendererInterface $templateRenderer,
        private HelpArticleTextFormatter $formatter,
    ) {}

    public function toPlainText(DocArticle $article): string
    {
        return $this->formatter->format($this->templateRenderer->render($article->template()));
    }
}
