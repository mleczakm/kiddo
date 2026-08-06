<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\RequestConnectionEnsurer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('unit')]
class RequestConnectionEnsurerTest extends TestCase
{
    public function testEnsureConnection(): void
    {
        $innerEnsurer = new class implements ConnectionEnsurerInterface {
            public bool $called = false;

            public function ensureConnection(): void
            {
                $this->called = true;
            }
        };

        $request = Request::create('any');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $requestConnectionEnsurer = new RequestConnectionEnsurer($innerEnsurer);
        $requestConnectionEnsurer->onKernelRequest($event);

        $this->assertTrue($innerEnsurer->called);
    }
}
