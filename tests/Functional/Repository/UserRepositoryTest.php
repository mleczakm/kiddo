<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->userRepository = $kernel->getContainer()
            ->get(UserRepository::class);
    }

    public function testFindByRole(): void
    {
        // Create test users with different roles using the existing assembler
        $adminUser = UserAssembler::new()
            ->withEmail('admin@example.com')
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $regularUser = UserAssembler::new()
            ->withEmail('user@example.com')
            ->withRoles('ROLE_USER')
            ->assemble();

        $anotherAdmin = UserAssembler::new()
            ->withEmail('admin2@example.com')
            ->withRoles('ROLE_ADMIN', 'ROLE_SUPER_ADMIN')
            ->assemble();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($adminUser);
        $entityManager->persist($regularUser);
        $entityManager->persist($anotherAdmin);
        $entityManager->flush();
        $entityManager->clear(); // Clear to ensure we're getting fresh data from the database

        // Test finding admin users
        $adminUsers = $this->userRepository->findByRole('ROLE_ADMIN');

        // Should find both admin users (one with only ROLE_ADMIN and one with multiple roles including ROLE_ADMIN)
        $this->assertCount(2, $adminUsers);

        // Verify the emails of the found admin users
        $emails = array_map(fn(User $user): string => $user->getEmail(), $adminUsers);
        $this->assertContains('admin@example.com', $emails);
        $this->assertContains('admin2@example.com', $emails);
        $this->assertNotContains('user@example.com', $emails);

        // Test finding regular users
        $regularUsers = $this->userRepository->findByRole('ROLE_USER');
        $this->assertCount(1, $regularUsers);
        $this->assertEquals('user@example.com', $regularUsers[0]->getEmail());

        // Test finding non-existent role
        $nonExistentRoleUsers = $this->userRepository->findByRole('ROLE_NON_EXISTENT');
        $this->assertCount(0, $nonExistentRoleUsers);
    }
}
