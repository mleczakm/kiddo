<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Infrastructure\EventSubscriber\MaliciousRequestSubscriber;
use App\Infrastructure\ZipBomb\ZipBombGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('unit')]
class MaliciousRequestSubscriberTest extends TestCase
{
    public function testOnKernelExceptionReturnsZipBombForMaliciousPattern(): void
    {
        $zipBombGenerator = new ZipBombGenerator();
        $subscriber = new MaliciousRequestSubscriber($zipBombGenerator);

        $request = Request::create('/.env');
        $exception = new NotFoundHttpException();
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/zip', $response->headers->get('Content-Type'));
        $this->assertEquals('attachment; filename="backup.zip"', $response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->getContent());
    }

    public function testOnKernelExceptionDoesNotInterveneForNonMaliciousPattern(): void
    {
        $zipBombGenerator = new ZipBombGenerator();
        $subscriber = new MaliciousRequestSubscriber($zipBombGenerator);

        $request = Request::create('/legitimate-page');
        $exception = new NotFoundHttpException();
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelExceptionDoesNotInterveneForNonNotFoundHttpException(): void
    {
        $zipBombGenerator = new ZipBombGenerator();
        $subscriber = new MaliciousRequestSubscriber($zipBombGenerator);

        $request = Request::create('/.env');
        $exception = new \RuntimeException('Some other exception');
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function maliciousPathsProvider(): array
    {
        return [
            'env file' => ['/.env'],
            'env with extension' => ['/.env.backup'],
            'phpinfo' => ['/phpinfo'],
            'phpinfo.php' => ['/phpinfo.php'],
            'wordpress path' => ['/wp-admin'],
            'aws config' => ['/.aws/credentials'],
            'vscode' => ['/.vscode/settings.json'],
            'sql file' => ['/dump.sql'],
            'backup config' => ['/backup_web_config.txt'],
            'sftp config' => ['/sftp-config.json'],
            'app dev' => ['/app_dev.php/something'],
        ];
    }

    #[DataProvider('maliciousPathsProvider')]
    public function testMaliciousPatternsAreDetected(string $path): void
    {
        $zipBombGenerator = new ZipBombGenerator();
        $subscriber = new MaliciousRequestSubscriber($zipBombGenerator);

        $request = Request::create($path);
        $exception = new NotFoundHttpException();
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->onKernelException($event);

        $this->assertNotNull($event->getResponse());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function legitimatePathsProvider(): array
    {
        return [
            'regular page' => ['/about'],
            'api endpoint' => ['/api/users'],
            'static asset' => ['/css/style.css'],
        ];
    }

    #[DataProvider('legitimatePathsProvider')]
    public function testLegitimatePathsAreNotIntercepted(string $path): void
    {
        $zipBombGenerator = new ZipBombGenerator();
        $subscriber = new MaliciousRequestSubscriber($zipBombGenerator);

        $request = Request::create($path);
        $exception = new NotFoundHttpException();
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }
}
