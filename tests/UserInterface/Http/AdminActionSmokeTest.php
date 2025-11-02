<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('smoke')]
class AdminActionSmokeTest extends WebTestCase
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
        $this->assertSelectorTextContains('h1', 'Panel Administratora');
    }

    public function testAdminLessonsTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/zajecia');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminScheduleTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/harmonogram');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/harmonogram"].bg-background');
    }

    public function testAdminTransfersTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/platnosci');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/platnosci"].bg-background');
    }

    public function testAdminBookingsTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/rezerwacje"].bg-background');
    }

    public function testAdminUsersTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/uzytkownicy"].bg-background');
    }

    public function testAdminMessagesTab(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin/wiadomosci');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/admin/wiadomosci"].bg-background');
        $this->assertSelectorTextContains('h3', 'Wiadomości od użytkowników');
    }

    public function testAllAdminTabsRequireAdminRole(): void
    {
        $client = static::createClient();
        $regularUser = $this->createRegularUser($client);
        $client->loginUser($regularUser);

        $adminRoutes = [
            '/admin',
            '/admin/zajecia',
            '/admin/harmonogram',
            '/admin/platnosci',
            '/admin/rezerwacje',
            '/admin/uzytkownicy',
            '/admin/wiadomosci',
        ];

        foreach ($adminRoutes as $route) {
            $client->request('GET', $route);
            $this->assertResponseStatusCodeSame(
                Response::HTTP_FOUND,
                "Route {$route} should be forbidden for regular users"
            );
        }
    }

    public function testTabNavigationLinksArePresent(): void
    {
        $client = static::createClient();
        $admin = $this->createAdminUser($client);
        $client->loginUser($admin);

        $client->request('GET', '/admin');

        // Check all navigation links are present
        $this->assertSelectorExists('a[href="/admin"]');
        $this->assertSelectorExists('a[href="/admin/zajecia"]');
        $this->assertSelectorExists('a[href="/admin/harmonogram"]');
        $this->assertSelectorExists('a[href="/admin/platnosci"]');
        $this->assertSelectorExists('a[href="/admin/rezerwacje"]');
        $this->assertSelectorExists('a[href="/admin/uzytkownicy"]');
        $this->assertSelectorExists('a[href="/admin/wiadomosci"]');
    }

    private function createAdminUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()
            ->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('admin@test.com')
            ->withName('Admin User')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createRegularUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()
            ->get(EntityManagerInterface::class);

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
