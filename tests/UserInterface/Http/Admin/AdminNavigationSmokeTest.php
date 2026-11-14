<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use PHPUnit\Framework\Attributes\Group;

#[Group('smoke')]
final class AdminNavigationSmokeTest extends AdminControllersSmokeTestCase
{
    public function testAdminDashboardWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testAdminBookingsWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminUsersWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminTransfersWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/platnosci');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminScheduleWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/harmonogram');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminSettingsWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/ustawienia');

        $this->assertResponseIsSuccessful();
    }

    public function testTabNavigationLinksArePresent(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        // Check all navigation links are present
        $this->assertSelectorExists('a[href="/admin"]');
        $this->assertSelectorExists('a[href="/admin/harmonogram"]');
        $this->assertSelectorExists('a[href="/admin/platnosci"]');
        $this->assertSelectorExists('a[href="/admin/rezerwacje"]');
        $this->assertSelectorExists('a[href="/admin/uzytkownicy"]');
        $this->assertSelectorExists('a[href="/admin/ustawienia"]');
        $this->assertSelectorExists('a[href="/admin/tresci"]');
    }

    public function testAllAdminEndpointsAccessibleToAdmin(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

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
            $this->assertResponseIsSuccessful("Route {$route} should be accessible to admin users");
        }
    }
}
