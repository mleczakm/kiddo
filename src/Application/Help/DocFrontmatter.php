<?php

declare(strict_types=1);

namespace App\Application\Help;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads and types the {@code {#--- ... ---#}} YAML frontmatter block at the top of
 * a help-article Twig template. Knows nothing about what the keys mean — that is
 * {@see DocRegistry}'s job.
 */
final readonly class DocFrontmatter
{
    private const string PATTERN = '/\A\s*\{#---\R(?<yaml>.*?)\R---#\}/s';

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private array $data,
    ) {}

    /**
     * @throws \RuntimeException when the block is missing or not valid YAML mapping
     */
    public static function fromFile(string $file): self
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read help article "%s".', $file));
        }

        $matches = [];
        if (preg_match(self::PATTERN, $contents, $matches) !== 1) {
            throw new \RuntimeException(sprintf(
                'Help article "%s" is missing a {#--- ... ---#} frontmatter block.',
                $file,
            ));
        }

        try {
            $parsed = Yaml::parse($matches['yaml']);
        } catch (ParseException $exception) {
            throw new \RuntimeException(
                sprintf('Help article "%s" has invalid frontmatter YAML.', $file),
                0,
                $exception,
            );
        }

        if (!is_array($parsed)) {
            throw new \RuntimeException(sprintf('Help article "%s" frontmatter must be a mapping.', $file));
        }

        /** @var array<string, mixed> $parsed */
        return new self($parsed);
    }

    /**
     * @throws \RuntimeException when the key is absent or blank
     */
    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Frontmatter key "%s" is required.', $key));
        }

        return trim($value);
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }

            $list[] = trim($item);
        }

        return $list;
    }
}
