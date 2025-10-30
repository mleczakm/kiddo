<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

#[Group('functional')]
final class AdminDashboardComponentTest extends WebTestCase
{
    use Factories;

    public function testAdminDashboardIsAccessibleToAdmin(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Panel Administratora');
        // Sidebar/menu rendered
        $this->assertSelectorExists('[data-testid="admin-menu"]');
        // Active link marked (dashboard)
        $this->assertSelectorExists('[data-testid="admin-menu"] a[aria-current="page"]');
    }

    public function testAdminDashboardRedirectsUnauthenticatedUser(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('http://localhost/login');
    }

    public function testUsersTabHighlightsInMenu(): void
    {
        $client = self::createClient();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $adminUser = new User();
        $adminUser->setEmail('admin@test.com');
        $adminUser->setName('Admin User');
        $adminUser->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);
        $client->request('GET', '/admin/uzytkownicy');

        $this->assertResponseIsSuccessful();
        // Sidebar/menu rendered
        $this->assertSelectorExists('[data-testid="admin-menu"]');
        // Ensure Users link has aria-current="page"
        $this->assertSelectorExists('[data-testid="admin-menu-users-link"][aria-current="page"]');
    }
}
