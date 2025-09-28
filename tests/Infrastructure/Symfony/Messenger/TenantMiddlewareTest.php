<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Messenger;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Tenant;
use App\Infrastructure\Symfony\Messenger\TenantMiddleware;
use App\Infrastructure\Symfony\Messenger\TenantStamp;
use App\Repository\TenantRepository;
use App\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Group('unit')]
final class TenantMiddlewareTest extends TestCase
{
    private function makeStack(callable $next): StackInterface
    {
        return new class ($next) implements StackInterface, MiddlewareInterface {
            public function __construct(
                /**
                 * @var callable(Envelope): void
                 */
                private $nextCallable
            ) {}

            public function next(): MiddlewareInterface
            {
                return $this;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->nextCallable)($envelope);
                return $envelope;
            }
        };
    }

    public function testItAddsTenantStampOnSend(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $context = new TenantContext($requestStack);
        $tenant = new Tenant('T1', 't1.test');
        $context->setTenant($tenant);

        $repo = $this->createMock(TenantRepository::class);
        $middleware = new TenantMiddleware($context, $repo);

        $message = new \stdClass();
        $envelope = new Envelope($message);

        $captured = null;
        $stack = $this->makeStack(function (Envelope $e) use (&$captured): void { $captured = $e; });

        $middleware->handle($envelope, $stack);

        self::assertNotNull($captured);
        $stamp = $captured->last(TenantStamp::class);
        self::assertInstanceOf(TenantStamp::class, $stamp);
        self::assertSame((string) $tenant->getId(), $stamp->tenantId);
    }

    public function testItSetsTenantOnReceiveAndRestoresPrevious(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $context = new TenantContext($requestStack);
        $tenantA = new Tenant('A', 'a.test');
        $tenantB = new Tenant('B', 'b.test');
        $context->setTenant($tenantA);

        $repo = $this->createMock(TenantRepository::class);
        $repo->method('find')
            ->willReturnCallback(fn(string $id) => ((string) $tenantB->getId()) === $id ? $tenantB : null);

        $middleware = new TenantMiddleware($context, $repo);

        $message = new \stdClass();
        $envelope = new Envelope($message)
            ->with(new TenantStamp((string) $tenantB->getId()))
            ->with(new ReceivedStamp('transport'));

        $assertedDuringHandling = false;
        $stack = $this->makeStack(function () use ($context, $tenantB, &$assertedDuringHandling): void {
            self::assertSame((string) $tenantB->getId(), (string) $context->getTenant()?->getId());
            $assertedDuringHandling = true;
        });

        $middleware->handle($envelope, $stack);

        self::assertTrue($assertedDuringHandling, 'Next middleware was not invoked');
        self::assertSame((string) $tenantA->getId(), (string) $context->getTenant()?->getId());
    }
}
