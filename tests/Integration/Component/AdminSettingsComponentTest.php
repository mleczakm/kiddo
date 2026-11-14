<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\Setting;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminSettingsComponent;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class AdminSettingsComponentTest extends AdminSettingsComponentTestCase
{
    public function testAdminRoleCanBeAddedToUser(): void
    {
        // Initially user has no admin role
        static::assertNotContains('ROLE_ADMIN', $this->regularUser->getRoles());

        // Add admin role
        $roles = $this->regularUser->getRoles();
        $roles[] = 'ROLE_ADMIN';
        $this->regularUser->setRoles($roles);
        $this->entityManager->flush();

        // Refresh from database
        $this->entityManager->refresh($this->regularUser);

        // Verify role was added
        static::assertContains('ROLE_ADMIN', $this->regularUser->getRoles());
    }

    public function testAdminRoleCanBeRemovedFromUser(): void
    {
        // Add admin role first
        $roles = $this->regularUser->getRoles();
        $roles[] = 'ROLE_ADMIN';
        $this->regularUser->setRoles($roles);
        $this->entityManager->flush();

        // Refresh from database
        $this->entityManager->refresh($this->regularUser);
        static::assertContains('ROLE_ADMIN', $this->regularUser->getRoles());

        // Remove admin role
        $roles = array_filter($this->regularUser->getRoles(), static fn($role) => $role !== 'ROLE_ADMIN');
        $this->regularUser->setRoles(array_values($roles));
        $this->entityManager->flush();

        // Refresh from database
        $this->entityManager->refresh($this->regularUser);

        // Verify role was removed
        static::assertNotContains('ROLE_ADMIN', $this->regularUser->getRoles());
    }

    public function testRobotsTxtCanBeSaved(): void
    {
        $content = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/";

        // Create or update robots.txt setting
        $robotsSetting = $this->settingRepository->findOneBy([
            'key' => 'robots.txt',
        ]);

        if ($robotsSetting === null) {
            $robotsSetting = new Setting();
            $robotsSetting->setKey('robots.txt');
            $this->entityManager->persist($robotsSetting);
        }

        $robotsSetting->setContent([
            'content' => $content,
        ]);
        $this->entityManager->flush();

        // Verify it was saved
        $robotsSetting = $this->settingRepository->findOneBy([
            'key' => 'robots.txt',
        ]);
        static::assertNotNull($robotsSetting);
        $savedContent = $robotsSetting->getContent();
        static::assertIsArray($savedContent);
        static::assertArrayHasKey('content', $savedContent);
        static::assertEquals($content, $savedContent['content']);
    }

    public function testAdminUsersListShowsExistingAdmins(): void
    {
        // Regression test: AdminSettingsComponent::loadSettings() used to
        // call a nonexistent findByRoles() (plural), which Doctrine's magic
        // finder silently turned into an exact-equality match on the whole
        // roles column — so this list was always empty, even for a real
        // ROLE_ADMIN user like $this->adminUser.
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $admins = $adminSettingsComponent->getAdminUsers();

        $adminIds = array_map(static fn(User $u) => $u->getId(), $admins);
        static::assertContains($this->adminUser->getId(), $adminIds);
        static::assertNotContains($this->regularUser->getId(), $adminIds);
    }

    public function testHostCanBeAddedAndRemovedThroughTheComponent(): void
    {
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);

        static::assertNotContains('ROLE_HOST', $this->regularUser->getRoles());

        $component->call('addHostUser', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        // The component's own dispatch may close/reopen the entity manager
        // (App\Infrastructure\Doctrine\EntityManagerResetter, needed for the
        // Swoole worker's long-running process); re-fetch rather than
        // refresh() the object captured in setUp(), which may now be
        // detached from a stale EntityManager reference.
        $reloaded = $this->userRepository->find($this->regularUser->getId());
        static::assertNotNull($reloaded);
        static::assertTrue($reloaded->hasRole('ROLE_HOST'));

        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $hosts = $adminSettingsComponent->getHostUsers();
        $hostIds = array_map(static fn(User $u) => $u->getId(), $hosts);
        static::assertContains($this->regularUser->getId(), $hostIds);

        $component->call('removeHostUser', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        // find() by primary key short-circuits to the identity map without
        // re-querying if $reloaded is still tracked; clear() forces a fresh
        // read so this actually observes removeHostUser()'s effect.
        $this->entityManager->clear();
        $reloadedAgain = $this->userRepository->find($this->regularUser->getId());
        static::assertNotNull($reloadedAgain);
        static::assertFalse($reloadedAgain->hasRole('ROLE_HOST'));
    }

    public function testAdminSearchFindsUsersByIdNameEmailOrChildName(): void
    {
        $withChild = UserAssembler::new()->withName('Anna Kowalska')->withEmail('anna@example.com')->assemble();
        $this->entityManager->persist($withChild);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('adminSearch', (string) $withChild->getId());
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForAdmin();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertContains($withChild->getId(), $ids);
    }

    public function testAdminSearchExcludesUsersAlreadyAdmins(): void
    {
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('adminSearch', 'admin@test.com');
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForAdmin();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertNotContains($this->adminUser->getId(), $ids);
    }

    public function testOrganizationDetailsCanBeSavedThroughTheComponent(): void
    {
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('organization', [
            'name' => 'Warsztatownia Testowa',
            'street' => 'ul. Przykładowa 5',
            'postal_code' => '01-234',
            'city' => 'Warszawa',
            'email' => 'biuro@example.com',
            'phone' => '+48 600 100 200',
            'bank_account' => '00 1111 2222 3333 4444 5555 6666',
            'blik_phone' => '600 100 200',
            'facebook_url' => 'https://www.facebook.com/warsztatownia',
        ]);

        $component->call('saveOrganizationDetails');

        $this->entityManager->clear();
        $setting = $this->settingRepository->findOneBy([
            'key' => 'organization_details',
        ]);
        static::assertNotNull($setting);
        $content = $setting->getContent();
        static::assertIsArray($content);
        static::assertSame('Warsztatownia Testowa', $content['name']);
        static::assertSame('biuro@example.com', $content['email']);
        static::assertSame('00 1111 2222 3333 4444 5555 6666', $content['bank_account']);
        static::assertSame('https://www.facebook.com/warsztatownia', $content['facebook_url']);
    }
}
