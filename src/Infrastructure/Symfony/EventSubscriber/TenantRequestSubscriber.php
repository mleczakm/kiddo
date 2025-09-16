<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\EventSubscriber;

use App\Tenant\TenantContext;
use App\Tenant\TenantResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest', priority: 32)]
final readonly class TenantRequestSubscriber
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $host = $request->getHost();
        $tenant = $this->resolver->resolveByHost($host);
        $this->context->setTenant($tenant);
        // Make tenant available on request attributes for stateless access
        if ($tenant !== null) {
            $request->attributes->set('_tenant', $tenant);
            $request->attributes->set('_tenant_id', (string) $tenant->getId());
        }
    }
}
