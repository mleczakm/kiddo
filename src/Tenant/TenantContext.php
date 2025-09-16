<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Entity\Tenant;
/**
 * Request-scoped context for current tenant.
 */
use Symfony\Component\HttpFoundation\RequestStack;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function __construct(
        private readonly RequestStack $requestStack
    ) {}

    public function setTenant(?Tenant $tenant): void
    {
        // Keep for BC, also mirror to current request attributes when possible
        $this->tenant = $tenant;
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $request->attributes->set('_tenant', $tenant);
            $request->attributes->set('_tenant_id', $tenant ? (string) $tenant->getId() : null);
        }
    }

    public function getTenant(): ?Tenant
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null && $request->attributes->has('_tenant')) {
            /** @var ?Tenant $t */
            $t = $request->attributes->get('_tenant');
            return $t;
        }
        return $this->tenant;
    }

    public function getEmailFrom(): ?string
    {
        return $this->getTenant()?->getEmailFrom();
    }

    public function getBlikPhone(): ?string
    {
        return $this->getTenant()?->getBlikPhone();
    }

    public function getTransferAccount(): ?string
    {
        return $this->getTenant()?->getTransferAccount();
    }
}
