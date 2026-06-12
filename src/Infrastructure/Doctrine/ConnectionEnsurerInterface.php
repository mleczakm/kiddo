<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

interface ConnectionEnsurerInterface
{
    public function ensureConnection(): void;
}
