<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Entity\Tenant;
use App\Repository\TenantRepository;

final readonly class TenantResolver
{
    public function __construct(
        private TenantRepository $tenants
    ) {}

    public function resolveByHost(?string $host): ?Tenant
    {
        if ($host === null || $host === '') {
            return null;
        }
        // Strip port if present
        $host = preg_replace('/:.+$/', '', $host) ?? $host;
        return $this->tenants->findOneByDomain($host);
    }
}
