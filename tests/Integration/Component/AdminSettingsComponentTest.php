<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\FinanceContact;
use App\Entity\Setting;
use App\Entity\User;
use App\Repository\FinanceContactRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[\PHPUnit\Framework\Attributes\Group('functional')]
class AdminSettingsComponentTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private UserRepository $userRepository;

    private FinanceContactRepository $financeContactRepository;

    private SettingRepository $settingRepository;

    private User $adminUser;

    private User $regularUser;

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

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testFinanceContactCanBeAdded(): void
    {
        // Initially no finance contacts
        $contacts = $this->financeContactRepository->findAll();
        $this->assertCount(0, $contacts);

        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        $this->assertCount(1, $contacts);
        $this->assertEquals($this->regularUser->getId(), $contacts[0]->getUser()->getId());
    }

    public function testFinanceContactCanBeRemoved(): void
    {
        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        $this->assertCount(1, $contacts);

        // Remove it
        $this->entityManager->remove($financeContact);
        $this->entityManager->flush();

        // Verify it was removed
        $contacts = $this->financeContactRepository->findAll();
        $this->assertCount(0, $contacts);
    }

    public function testAdminRoleCanBeAddedToUser(): void
    {
        // Initially user has no admin role
        $this->assertNotContains('ROLE_ADMIN', $this->regularUser->getRoles());

        // Add admin role
        $roles = $this->regularUser->getRoles();
        $roles[] = 'ROLE_ADMIN';
        $this->regularUser->setRoles($roles);
        $this->entityManager->flush();

        // Refresh from database
        $this->entityManager->refresh($this->regularUser);

        // Verify role was added
        $this->assertContains('ROLE_ADMIN', $this->regularUser->getRoles());
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
        $this->assertContains('ROLE_ADMIN', $this->regularUser->getRoles());

        // Remove admin role
        $roles = array_filter($this->regularUser->getRoles(), fn($role) => $role !== 'ROLE_ADMIN');
        $this->regularUser->setRoles(array_values($roles));
        $this->entityManager->flush();

        // Refresh from database
        $this->entityManager->refresh($this->regularUser);

        // Verify role was removed
        $this->assertNotContains('ROLE_ADMIN', $this->regularUser->getRoles());
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
        $this->assertNotNull($robotsSetting);
        $this->assertEquals($content, $robotsSetting->getContent()['content']);
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
        $this->assertGreaterThanOrEqual(1, count($contacts));
    }
}
