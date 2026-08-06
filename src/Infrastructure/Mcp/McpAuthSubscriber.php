<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Guards the Symfony MCP Bundle HTTP endpoint with optional service key + chat feature flag.
 */
final readonly class McpAuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FeatureManager $featureManager,
        #[Autowire('%env(KIDDO_MCP_SERVICE_KEY)%')]
        private string $serviceKey,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
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

        if (! $this->featureManager->isEnabled('chat_assistant')) {
            $event->setResponse(new JsonResponse([
                'error' => 'Chat assistant is disabled',
            ], 404));

            return;
        }

        if ($this->serviceKey === '') {
            return;
        }

        $key = $request->headers->get('X-Kiddo-Mcp-Key')
            ?? $request->headers->get('X-Api-Key');
        if (! is_string($key) || $key === '') {
            $authorization = $request->headers->get('Authorization');
            if (is_string($authorization) && preg_match('/^Bearer\s+(\S+)/i', $authorization, $matches) === 1) {
                $key = $matches[1];
            }
        }

        if (! is_string($key) || ! hash_equals($this->serviceKey, $key)) {
            $event->setResponse(new JsonResponse([
                'error' => 'Invalid MCP service key',
            ], 401));
        }
    }
}
