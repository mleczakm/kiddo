<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Stimulus has no default click event for non-form/button/anchor elements (div, tr, li, p),
 * so `data-action="live#action"` (or live#emit, live#emitUp, ...) on those silently never
 * binds - clicking does nothing, no error, no request. It must be `data-action="click->live#..."`.
 * This bit every row that opens the reservation details modal from a list (dashboard, lesson
 * detail, series detail) after a refactor introduced the un-prefixed form everywhere but two
 * spots, and again on the click-to-edit fields on the admin user detail page.
 */
#[Group('unit')]
final class StimulusEmitActionPrefixTest extends TestCase
{
    public function testNoTemplateUsesAnUnprefixedLiveAction(): void
    {
        $templatesDir = dirname(__DIR__) . '/templates';
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($templatesDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }

            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.html.twig')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            // Elements Stimulus maps to a default event that a bare "live#*" can
            // rely on without the "click->" prefix: "a"/"button" default to click,
            // "form" defaults to submit (the common, usually-intended case for a
            // form's own data-action).
            $tagsWithDefaultClick = ['a', 'button', 'form'];

            $matchCount = preg_match_all('/data-action="([^"]*)"/', $contents, $matches, \PREG_OFFSET_CAPTURE);
            if ($matchCount !== false && $matchCount > 0) {
                foreach ($matches[1] as [$actionValue, $offset]) {
                    $hasBareLiveAction = false;
                    $directives = preg_split('/\s+/', trim($actionValue));
                    if ($directives === false) {
                        continue;
                    }
                    foreach ($directives as $directive) {
                        if ($directive !== '' && str_starts_with($directive, 'live#')) {
                            $hasBareLiveAction = true;
                        }
                    }
                    if (! $hasBareLiveAction) {
                        continue;
                    }

                    $precedingTagOpen = strrpos(substr($contents, 0, $offset), '<');
                    $tagName = null;
                    if ($precedingTagOpen !== false && preg_match(
                        '/<([a-zA-Z0-9]+)/',
                        substr($contents, $precedingTagOpen),
                        $tagMatch
                    )) {
                        $tagName = strtolower($tagMatch[1]);
                    }

                    if (in_array($tagName, $tagsWithDefaultClick, true)) {
                        continue;
                    }

                    $offenders[] = $file->getPathname() . ' -> <' . ($tagName ?? '?') . '> data-action="' . $actionValue . '"';
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Found un-prefixed 'live#*' Stimulus actions (needs 'click->' prefix on div/tr/li/p elements):\n" . implode(
                "\n",
                $offenders
            )
        );
    }
}
