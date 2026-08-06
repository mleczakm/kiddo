<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

final readonly class CompositeConnectionEnsurer implements ConnectionEnsurerInterface
{
    /**
     * @param list<ConnectionEnsurerInterface> $ensurers
     */
    public function __construct(
        private array $ensurers,
    ) {}

    public function ensureConnection(): void
    {
        foreach ($this->ensurers as $ensurer) {
            $ensurer->ensureConnection();
        }
    }
}
