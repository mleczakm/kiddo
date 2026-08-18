<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Guards the Symfony MCP Bundle HTTP endpoint with optional service key + chat feature flag.
 */
final readonly class McpAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FeatureManager $featureManager,
        #[Autowire('%env(KIDDO_MCP_SERVICE_KEY)%')]
        private string $serviceKey,
        #[Autowire(service: 'limiter.mcp_ip_limiter')]
        private RateLimiterFactory $mcpRateLimiter,
    ) {}

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== '_mcp_endpoint') {
            return;
        }

        // CORS / MCP preflight must not require the service key.
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        if (!$this->featureManager->isEnabled('chat_assistant')) {
            $event->setResponse(new JsonResponse([
                'error' => 'Chat assistant is disabled',
            ], 404));

            return;
        }

        // Apply IP-based rate limiting
        $limiter = $this->mcpRateLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $event->setResponse(new JsonResponse([
                'error' => 'Rate limit exceeded',
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ], 429));

            return;
        }

        // If no service key is configured, allow public access
        if ($this->serviceKey === '') {
            return;
        }

        // If service key is configured, require it (for ElevenLabs)
        $key = $request->headers->get('X-Kiddo-Mcp-Key') ?? $request->headers->get('X-Api-Key');
        if (!is_string($key) || $key === '') {
            $authorization = $request->headers->get('Authorization');
            if (is_string($authorization) && preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
                $key = $matches[1];
            }
        }

        if (!is_string($key) || !hash_equals($this->serviceKey, $key)) {
            $event->setResponse(new JsonResponse([
                'error' => 'Invalid MCP service key',
            ], 401));
        }
    }
}
