<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Tests\Assembler\UserAssembler;
use PHPUnit\Framework\Attributes\Group;

#[Group('smoke')]
final class AdminBookingsAccessTest extends AdminBookingsTabTestCase
{
    public function testAdminBookingsPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        // Access the admin bookings page
        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(
            '.rounded-2xl.border.border-slate-200',
            'Admin bookings component should be present',
        );
        $this->assertSelectorTextContains('h3', 'Wszystkie rezerwacje');
    }

    public function testAdminBookingsPageRedirectsUnauthenticatedUsers(): void
    {
        $client = static::createClient();

        // Try to access admin page without authentication
        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseRedirects();
    }
}
