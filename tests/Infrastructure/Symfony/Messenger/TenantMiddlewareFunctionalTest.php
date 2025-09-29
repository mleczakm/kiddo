<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Messenger;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Tenant;
use App\Infrastructure\Symfony\Messenger\TenantMiddleware;
use App\Infrastructure\Symfony\Messenger\TenantStamp;
use App\Repository\TenantRepository;
use App\Tenant\TenantContext;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

#[Group('functional')]
final class TenantMiddlewareFunctionalTest extends KernelTestCase
{
    private TenantContext $tenantContext;

    private TenantRepository $tenantRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->tenantContext = self::getContainer()->get(TenantContext::class);
        $this->tenantRepository = self::getContainer()->get(TenantRepository::class);
        // Make sure there is a current request so TenantContext mirrors attributes
        /** @var RequestStack $rs */
        $rs = self::getContainer()->get(RequestStack::class);
        $rs->push(Request::create('https://t-mid.test/'));
    }

    private function makeStack(callable $next): StackInterface
    {
        return new class ($next) implements StackInterface, MiddlewareInterface {
            /**
             * @param callable(Envelope):void $next
             */
            public function __construct(
                private $next
            ) {}

            public function next(): MiddlewareInterface
            {
                return $this;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->next)($envelope);
                return $envelope;
            }
        };
    }

    public function testSendingSideAddsStampWhenTenantInContext(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $tenant = new Tenant('T-MID', 't-mid.test');
        $em->persist($tenant);
        $em->flush();

        $this->tenantContext->setTenant($tenant);
        $middleware = new TenantMiddleware($this->tenantContext, $this->tenantRepository);

        $envelope = new Envelope(new \stdClass());
        $captured = null;
        $stack = $this->makeStack(function (Envelope $e) use (&$captured): void { $captured = $e; });

        $middleware->handle($envelope, $stack);

        Assert::assertNotNull($captured);
        $stamp = $captured->last(TenantStamp::class);
        Assert::assertInstanceOf(TenantStamp::class, $stamp);
        Assert::assertSame((string) $tenant->getId(), $stamp->tenantId);
    }

    public function testReceiveSideSetsAndRestoresTenant(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $tenantA = new Tenant('A', 'a.mid');
        $tenantB = new Tenant('B', 'b.mid');
        $em->persist($tenantA);
        $em->persist($tenantB);
        $em->flush();

        $this->tenantContext->setTenant($tenantA);
        $middleware = new TenantMiddleware($this->tenantContext, $this->tenantRepository);

        $envelope = new Envelope(new \stdClass())
            ->with(new TenantStamp((string) $tenantB->getId()))
            ->with(new ReceivedStamp('async'));

        $asserted = false;
        $stack = $this->makeStack(function () use (&$asserted, $tenantB): void {
            $current = $this->tenantContext->getTenant();
            Assert::assertNotNull($current);
            Assert::assertSame((string) $tenantB->getId(), (string) $current->getId());
            $asserted = true;
        });

        $middleware->handle($envelope, $stack);

        Assert::assertTrue($asserted);
        Assert::assertSame((string) $tenantA->getId(), (string) $this->tenantContext->getTenant()?->getId());
    }
}
