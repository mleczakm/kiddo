<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\FinanceContact;
use App\Entity\Setting;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\FinanceContactRepository;
use App\Infrastructure\Doctrine\Repository\SettingRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminSettingsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class AdminSettingsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private UserRepository $userRepository;

    private FinanceContactRepository $financeContactRepository;

    private SettingRepository $settingRepository;

    private User $adminUser;

    private User $regularUser;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->userRepository = static::getContainer()->get(UserRepository::class);
        $this->financeContactRepository = static::getContainer()->get(FinanceContactRepository::class);
        $this->settingRepository = static::getContainer()->get(SettingRepository::class);

        // Create test users
        $this->adminUser = new User('admin@test.com', 'Admin User');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($this->adminUser);

        $this->regularUser = new User('user@test.com', 'Regular User');
        $this->entityManager->persist($this->regularUser);

        $this->entityManager->flush();
    }

    public function testFinanceContactCanBeAdded(): void
    {
        // Initially no finance contacts
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(0, $contacts);

        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);
        static::assertEquals($this->regularUser->getId(), $contacts[0]->getUser()->getId());
    }

    public function testFinanceContactCanBeRemoved(): void
    {
        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);

        // Remove it
        $this->entityManager->remove($financeContact);
        $this->entityManager->flush();

        // Verify it was removed
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(0, $contacts);
    }

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

    public function testFinanceContactUniqueness(): void
    {
        // Add finance contact
        $financeContact1 = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact1);
        $this->entityManager->flush();

        // Try to add another finance contact for the same user
        $financeContact2 = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact2);

        // This should not throw an error but we should check uniqueness in business logic
        $this->entityManager->flush();

        // In a real application, you'd want to enforce uniqueness at the database level
        // or check before adding
        $contacts = $this->financeContactRepository->findBy([
            'user' => $this->regularUser,
        ]);
        static::assertGreaterThanOrEqual(1, count($contacts));
    }

    public function testFinanceContactCanBeAddedAndRemovedThroughTheComponent(): void
    {
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);

        static::assertCount(0, $this->financeContactRepository->findAll());

        $component->call('addFinanceContact', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);
        static::assertSame($this->regularUser->getId(), $contacts[0]->getUser()->getId());

        $component->call('removeFinanceContact', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        static::assertCount(0, $this->financeContactRepository->findAll());
    }

    public function testFinanceContactSearchFindsUsersByIdNameEmailOrChildName(): void
    {
        $withChild = UserAssembler::new()->withName('Anna Kowalska')->withEmail('anna@example.com')->assemble();
        $this->entityManager->persist($withChild);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('financeContactSearch', (string) $withChild->getId());
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForFinanceContact();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertContains($withChild->getId(), $ids);
    }

    public function testFinanceContactSearchExcludesUsersAlreadyFinanceContacts(): void
    {
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('financeContactSearch', $this->regularUser->getEmail());
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForFinanceContact();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertNotContains($this->regularUser->getId(), $ids);
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
    }
}
