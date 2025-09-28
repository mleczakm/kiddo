<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Messenger;

use App\Entity\Tenant;
use App\Repository\TenantRepository;
use App\Tenant\TenantContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Uid\Ulid;

/**
 * Messenger middleware to propagate tenant context across async boundaries.
 */
final readonly class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $tenantContext,
        private TenantRepository $tenantRepository,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $isReceiving = $envelope->last(ReceivedStamp::class) !== null;

        if ($isReceiving) {
            // Worker side: set tenant in context based on stamp for the duration of handling
            $stamp = $envelope->last(TenantStamp::class);
            $previous = $this->tenantContext->getTenant();

            try {
                if ($stamp instanceof TenantStamp) {
                    $tenant = $this->loadTenant($stamp->tenantId);
                    $this->tenantContext->setTenant($tenant);
                } else {
                    // No stamp means no tenant; make sure context is cleared to avoid leakage
                    $this->tenantContext->setTenant(null);
                }

                return $stack->next()
                    ->handle($envelope, $stack);
            } finally {
                // Restore previous tenant after handling
                $this->tenantContext->setTenant($previous instanceof Tenant ? $previous : null);
            }
        }

        // Sending side: attach tenant stamp if not present
        if ($envelope->last(TenantStamp::class) === null) {
            $tenant = $this->tenantContext->getTenant();
            if ($tenant instanceof Tenant) {
                $envelope = $envelope->with(new TenantStamp((string) $tenant->getId()));
            }
        }

        return $stack->next()
            ->handle($envelope, $stack);
    }

    private function loadTenant(string $tenantId): ?Tenant
    {
        if (! Ulid::isValid($tenantId)) {
            return $this->tenantRepository->findOneByDomain($tenantId);
        }

        // find() supports Ulid strings
        return $this->tenantRepository->find($tenantId);
    }
}
