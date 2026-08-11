<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Mcp;

use App\Infrastructure\Mcp\McpAuthSubscriber;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[Group('unit')]
final class McpAuthSubscriberTest extends TestCase
{
    public function testAllowsRequestWhenServiceKeyIsEmpty(): void
    {
        $subscriber = $this->createSubscriber(serviceKey: '');
        $event = $this->createRequestEvent($subscriber, headers: []);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRejectsRequestWithoutKeyWhenServiceKeyConfigured(): void
    {
        $subscriber = $this->createSubscriber(serviceKey: 'secret-key');
        $event = $this->createRequestEvent($subscriber, headers: []);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(401, $event->getResponse()->getStatusCode());
    }

    public function testAllowsRequestWithMatchingXKiddoMcpKeyHeader(): void
    {
        $subscriber = $this->createSubscriber(serviceKey: 'secret-key');
        $event = $this->createRequestEvent($subscriber, headers: [
            'X-Kiddo-Mcp-Key' => 'secret-key',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsRequestWithMatchingBearerAuthorizationHeader(): void
    {
        $subscriber = $this->createSubscriber(serviceKey: 'secret-key');
        $event = $this->createRequestEvent($subscriber, headers: [
            'Authorization' => 'Bearer secret-key',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRejectsRequestWithWrongKey(): void
    {
        $subscriber = $this->createSubscriber(serviceKey: 'secret-key');
        $event = $this->createRequestEvent($subscriber, headers: [
            'X-Kiddo-Mcp-Key' => 'wrong-key',
        ]);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(401, $event->getResponse()->getStatusCode());
    }

    public function testEnforcesIpRateLimit(): void
    {
        $limiter = new RateLimiterFactory([
            'id' => 'mcp_ip_limiter',
            'policy' => 'sliding_window',
            'limit' => 2,
            'interval' => '1 hour',
        ], new InMemoryStorage());

        $subscriber = new McpAuthSubscriber($this->featureManagerAlwaysEnabled(), '', $limiter);

        $event1 = $this->createRequestEvent($subscriber, headers: []);
        $subscriber->onKernelRequest($event1);
        self::assertNull($event1->getResponse());

        $event2 = $this->createRequestEvent($subscriber, headers: []);
        $subscriber->onKernelRequest($event2);
        self::assertNull($event2->getResponse());

        $event3 = $this->createRequestEvent($subscriber, headers: []);
        $subscriber->onKernelRequest($event3);
        self::assertNotNull($event3->getResponse());
        self::assertSame(429, $event3->getResponse()->getStatusCode());
    }

    private function createSubscriber(string $serviceKey): McpAuthSubscriber
    {
        $limiter = new RateLimiterFactory([
            'id' => 'mcp_ip_limiter',
            'policy' => 'sliding_window',
            'limit' => 1000,
            'interval' => '1 hour',
        ], new InMemoryStorage());

        return new McpAuthSubscriber($this->featureManagerAlwaysEnabled(), $serviceKey, $limiter);
    }

    private function featureManagerAlwaysEnabled(): FeatureManager
    {
        $featureManager = $this->createMock(FeatureManager::class);
        $featureManager->method('isEnabled')
            ->willReturn(true);

        return $featureManager;
    }

    /**
     * @param array<string, string> $headers
     */
    private function createRequestEvent(McpAuthSubscriber $subscriber, array $headers): RequestEvent
    {
        $request = Request::create('/api/mcp', 'POST');
        $request->attributes->set('_route', '_mcp_endpoint');
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $kernel = $this->createMock(KernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
