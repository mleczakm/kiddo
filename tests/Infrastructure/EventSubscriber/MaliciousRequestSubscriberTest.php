<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\MaliciousRequestSubscriber;
use App\Infrastructure\Security\HoneypotResponder;
use App\Infrastructure\Security\MaliciousRequestPathMatcher;
use App\Infrastructure\ZipBomb\ZipBombGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('unit')]
final class MaliciousRequestSubscriberTest extends TestCase
{
    private MaliciousRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new MaliciousRequestSubscriber(
            new MaliciousRequestPathMatcher(),
            new HoneypotResponder(new ZipBombGenerator()),
        );
    }

    public function testOnKernelRequestReturnsZipBombForMaliciousPattern(): void
    {
        $request = Request::create('/wdone1.php');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
    }

    public function testOnKernelRequestDoesNotInterveneForLegitimatePath(): void
    {
        $request = Request::create('/panel');
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelExceptionReturnsZipBombForMaliciousPattern(): void
    {
        $request = Request::create('/.env');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );

        $this->subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="backup.zip"', $response->headers->get('Content-Disposition'));
    }

    public function testOnKernelExceptionDoesNotInterveneForNonMaliciousPattern(): void
    {
        $request = Request::create('/legitimate-page');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(),
        );

        $this->subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelExceptionDoesNotInterveneForNonNotFoundHttpException(): void
    {
        $request = Request::create('/.env');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('Some other exception'),
        );

        $this->subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function maliciousPathsProvider(): array
    {
        return [
            'env file' => ['/.env'],
            'php scanner wdone1' => ['/wdone1.php'],
            'php scanner root' => ['/root.php'],
            'php scanner a7' => ['/a7.php'],
            'php scanner xxa' => ['/xxa.php'],
            'php scanner wert' => ['/wert.php'],
            'php scanner academy' => ['/academy.php'],
            'php scanner js' => ['/js.php'],
            'php scanner lol' => ['/lol.php'],
            'php scanner 100' => ['/100.php'],
            'php scanner with trailing slash' => ['/wdone1.php/'],
            'wordpress path' => ['/wp-admin'],
            'aws config' => ['/.aws/credentials'],
            'sql file' => ['/dump.sql'],
        ];
    }

    #[DataProvider('maliciousPathsProvider')]
    public function testOnKernelRequestDetectsMaliciousPatterns(string $path): void
    {
        $request = Request::create($path);
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->subscriber->onKernelRequest($event);

        $this->assertNotNull($event->getResponse());
    }
}
