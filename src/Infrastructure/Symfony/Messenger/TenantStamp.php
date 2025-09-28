<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries tenant identifier across Messenger transports.
 */
final readonly class TenantStamp implements StampInterface
{
    public function __construct(
        public string $tenantId,
    ) {}
}
