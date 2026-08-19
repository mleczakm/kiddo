<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('smoke')]
class AdminControllersSmokeTest extends WebTestCase
{
    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects();
    }

    public function testAdminDashboardWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testAdminBookingsRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminBookingsWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminUsersRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminUsersWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminTransfersRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/platnosci');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminTransfersWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/platnosci');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminScheduleRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/harmonogram');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAdminScheduleWithAdminUser(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/harmonogram');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminSettingsRequiresAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $client->request('GET', '/admin/ustawienia');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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

    private function createAdminUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('admin@test.com')
            ->withName('Admin User')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createHostUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('host@test.com')
            ->withName('Host User')
            ->withRoles('ROLE_HOST')
            ->assemble();

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRegularUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('user@test.com')
            ->withName('Regular User')
            ->withRoles('ROLE_USER')
            ->assemble();

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
