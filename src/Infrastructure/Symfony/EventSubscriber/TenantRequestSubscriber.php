<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\EventSubscriber;

use App\Tenant\TenantContext;
use App\Repository\TenantRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest', priority: 32)]
final readonly class TenantRequestSubscriber
{
    public function __construct(
        private TenantRepository $tenants,
        private TenantContext $context,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $tenant = $this->tenants->findOneBy([]);
        $this->context->setTenant($tenant);
        // Make tenant available on request attributes for stateless access
        if ($tenant !== null) {
            $request = $event->getRequest();
            $request->attributes->set('_tenant', $tenant);
            $request->attributes->set('_tenant_id', (string) $tenant->getId());
        }
    }
}
