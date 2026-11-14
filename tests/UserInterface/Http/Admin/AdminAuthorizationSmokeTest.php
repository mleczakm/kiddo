<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

#[Group('smoke')]
final class AdminAuthorizationSmokeTest extends AdminControllersSmokeTestCase
{
    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects();
    }

    public function testAdminBookingsRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminUsersRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminTransfersRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/platnosci');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminScheduleRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/harmonogram');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminSettingsRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/ustawienia');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAllAdminEndpointsRequireAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $adminRoutes = [
            '/admin',
            '/admin/harmonogram',
            '/admin/platnosci',
            '/admin/rezerwacje',
            '/admin/uzytkownicy',
            '/admin/ustawienia',
            '/admin/tresci',
        ];

        foreach ($adminRoutes as $route) {
            $client->request('GET', $route);
            $this->assertResponseStatusCodeSame(
                Response::HTTP_FORBIDDEN,
                "Route {$route} should be forbidden for regular users",
            );
        }
    }

    public function testHostCanReachLessonsAndBookingsOnly(): void
    {
        $client = static::createClient();
        $host = $this->createHostUser($client);
        $client->loginUser($host);

        foreach (['/admin/harmonogram', '/admin/rezerwacje'] as $route) {
            $client->request('GET', $route);
            $this->assertResponseIsSuccessful("Route {$route} should be accessible to a host");
        }
    }

    public function testHostIsForbiddenFromAdminOnlySections(): void
    {
        $client = static::createClient();
        $host = $this->createHostUser($client);
        $client->loginUser($host);

        $adminOnlyRoutes = ['/admin', '/admin/platnosci', '/admin/uzytkownicy', '/admin/ustawienia', '/admin/tresci'];

        foreach ($adminOnlyRoutes as $route) {
            $client->request('GET', $route);
            $this->assertResponseStatusCodeSame(
                Response::HTTP_FORBIDDEN,
                "Route {$route} should be forbidden for a host",
            );
        }
    }
}
