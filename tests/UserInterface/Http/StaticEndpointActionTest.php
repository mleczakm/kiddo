<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use App\UserInterface\Http\StaticEndpointAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('unit')]
class StaticEndpointActionTest extends TestCase
{
    public function testInvokeReturnsContentFromSetting(): void
    {
        $setting = $this->createMock(Setting::class);
        $setting->method('getContent')
            ->willReturn([
                'content_type' => 'text/plain',
                'body' => 'User-agent: *Disallow: /',
            ]);

        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('findOneByKey')
            ->with('static_endpoint_robots.txt')
            ->willReturn($setting);

        $action = new StaticEndpointAction($settingRepository);
        $request = Request::create('/robots.txt');
        $response = $action($request, 'robots.txt');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
        $this->assertEquals('User-agent: *Disallow: /', $response->getContent());
    }

    public function testInvokeReturns404WhenSettingNotFound(): void
    {
        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('findOneByKey')
            ->with('static_endpoint_robots.txt')
            ->willReturn(null);

        $action = new StaticEndpointAction($settingRepository);
        $request = Request::create('/robots.txt');
        $response = $action($request, 'robots.txt');

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertEquals('Not found', $response->getContent());
    }

    public function testInvokeHandlesMissingContentType(): void
    {
        $setting = $this->createMock(Setting::class);
        $setting->method('getContent')
            ->willReturn([
                'body' => 'Some content',
            ]);

        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('findOneByKey')
            ->willReturn($setting);

        $action = new StaticEndpointAction($settingRepository);
        $request = Request::create('/sitemap.xml');
        $response = $action($request, 'sitemap.xml');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('Content-Type')); // Default
        $this->assertEquals('Some content', $response->getContent());
    }

    public function testInvokeHandlesMissingBody(): void
    {
        $setting = $this->createMock(Setting::class);
        $setting->method('getContent')
            ->willReturn([
                'content_type' => 'application/xml',
            ]);

        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('findOneByKey')
            ->willReturn($setting);

        $action = new StaticEndpointAction($settingRepository);
        $request = Request::create('/security.txt');
        $response = $action($request, 'security.txt');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/xml', $response->headers->get('Content-Type'));
        $this->assertEquals('', $response->getContent()); // Empty body
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validStaticEndpointsProvider(): array
    {
        return [
            'robots.txt' => ['robots.txt'],
            'sitemap.xml' => ['sitemap.xml'],
            'security.txt' => ['security.txt'],
        ];
    }

    #[DataProvider('validStaticEndpointsProvider')]
    public function testValidStaticEndpoints(string $path): void
    {
        $setting = $this->createMock(Setting::class);
        $setting->method('getContent')
            ->willReturn([
                'content_type' => 'text/plain',
                'body' => 'Test content',
            ]);

        $settingRepository = $this->createMock(SettingRepository::class);
        $settingRepository->method('findOneByKey')
            ->with('static_endpoint_' . $path)
            ->willReturn($setting);

        $action = new StaticEndpointAction($settingRepository);
        $request = Request::create('/' . $path);
        $response = $action($request, $path);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}
