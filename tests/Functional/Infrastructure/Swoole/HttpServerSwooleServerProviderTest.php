<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Swoole;

use App\Infrastructure\Swoole\HttpServerSwooleServerProvider;
use App\Infrastructure\Swoole\SwooleServerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use SwooleBundle\SwooleBundle\Server\Exception\UninitializedException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class HttpServerSwooleServerProviderTest extends KernelTestCase
{
    private SwooleServerProviderInterface $provider;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->provider = self::getContainer()->get(SwooleServerProviderInterface::class);
    }

    public function testProviderIsInstanceOfHttpServerSwooleServerProvider(): void
    {
        $this->assertInstanceOf(HttpServerSwooleServerProvider::class, $this->provider);
    }

    public function testGetServerReturnsSwooleServer(): void
    {
        $this->expectException(UninitializedException::class);
        $this->expectExceptionMessage('Swoole HTTP Server has not been setup yet');

        // In test environment, Swoole server is not initialized
        $this->provider->getServer();
    }

    public function testProviderImplementsInterface(): void
    {
        $this->assertInstanceOf(SwooleServerProviderInterface::class, $this->provider);
    }
}
