<?php

declare(strict_types=1);

namespace App\UserInterface\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Estimated reading time from sanitized article HTML. Computed at render
 * time from plain-text content, never stored — editing the article keeps
 * this figure honest without a migration or a stale cached value.
 */
final class ReadingTimeExtension extends AbstractExtension
{
    private const int WORDS_PER_MINUTE = 200;

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('reading_time', $this->estimate(...)),
            new TwigFilter('format_bytes', $this->formatBytes(...)),
        ];
    }

    public function estimate(?string $html): int
    {
        if ($html === null || $html === '') {
            return 1;
        }

        $text = trim(strip_tags($html));
        $wordCount = $text === '' ? 0 : \count(preg_split('/\s+/u', $text) ?: []);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < (1024 * 1024)) {
            return \max(1, (int) round($bytes / 1024)) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
