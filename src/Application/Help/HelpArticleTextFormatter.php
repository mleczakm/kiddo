<?php

declare(strict_types=1);

namespace App\Application\Help;

/**
 * Flattens a rendered help-article HTML fragment into readable plain text for the
 * chat assistant: headings become "## ", list items "- ", and links keep their
 * (already resolved) target as "label (url)". Whitespace is collapsed so the body
 * stays compact in a tool result.
 */
final readonly class HelpArticleTextFormatter
{
    public function format(string $html): string
    {
        $text =
            preg_replace_callback(
                '/<a\b[^>]*\bhref="([^"]*)"[^>]*>(.*?)<\/a>/is',
                static function (array $match): string {
                    $label = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5));
                    $href = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));

                    return $href === '' || $href === $label ? $label : sprintf('%s (%s)', $label, $href);
                },
                $html,
            ) ?? $html;

        $text = preg_replace('/<h[1-6]\b[^>]*>/i', "\n\n## ", $text) ?? $text;
        $text = preg_replace('/<li\b[^>]*>/i', "\n- ", $text) ?? $text;
        $text = str_replace(
            [
                '</p>',
                '</h1>',
                '</h2>',
                '</h3>',
                '</h4>',
                '</h5>',
                '</h6>',
                '</li>',
                '</ul>',
                '</ol>',
                '<br>',
                '<br/>',
                '<br />',
            ],
            "\n",
            $text,
        );

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
