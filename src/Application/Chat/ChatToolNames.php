<?php

declare(strict_types=1);

namespace App\Application\Chat;

final class ChatToolNames
{
    /**
     * @param iterable<ChatToolProviderInterface> $providers
     */
    public function resolve(string $name, iterable $providers): ?string
    {
        $normalized = $this->normalizeIncomingName($name);
        $map = $this->aliasMap($providers);

        return $map[$normalized] ?? $map[str_replace('.', '_', $normalized)] ?? null;
    }

    /**
     * Incoming aliases accepted on call (not all are advertised on MCP).
     *
     * @param iterable<ChatToolProviderInterface> $providers
     * @return array<string, string> alias → canonical
     */
    private function aliasMap(iterable $providers): array
    {
        $map = [];
        foreach ($providers as $provider) {
            foreach ($provider->definitions() as $definition) {
                $canonical = $definition->name;
                $candidates = [$canonical, str_replace('.', '_', $canonical)];
                foreach ($candidates as $alias) {
                    if ($alias === '') {
                        continue;
                    }
                    $map[$this->normalizeIncomingName($alias)] = $canonical;
                }
            }
        }

        return $map;
    }

    private function normalizeIncomingName(string $name): string
    {
        $name = trim($name);
        foreach (['Warsztatownia_', 'Kiddo_', 'kiddo_'] as $prefix) {
            if (!str_starts_with($name, $prefix)) {
                continue;
            }

            $name = substr($name, strlen($prefix));
            break;
        }

        return $name;
    }
}
