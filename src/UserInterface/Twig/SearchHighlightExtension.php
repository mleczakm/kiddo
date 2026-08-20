<?php

declare(strict_types=1);

namespace App\UserInterface\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class SearchHighlightExtension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('highlight_search', $this->highlight(...), [
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function highlight(string $value, string $query): Markup
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $query = trim($query);
        if ($query === '') {
            return new Markup($escaped, 'UTF-8');
        }

        $highlighted = preg_replace(
            '/(' . preg_quote(htmlspecialchars($query, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/') . ')/iu',
            '<mark class="bg-amber-200 text-inherit rounded-sm px-0.5">$1</mark>',
            $escaped,
        );

        return new Markup($highlighted ?? $escaped, 'UTF-8');
    }
}
