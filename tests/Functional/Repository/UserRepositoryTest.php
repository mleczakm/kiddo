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
        $this->userRepository = self::getContainer()
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

    public function testFindAllMatching(): void
    {
        $user1 = UserAssembler::new()
            ->withName('John Doe')
            ->withEmail('john.doe@example.com')
            ->assemble();
        $user2 = UserAssembler::new()
            ->withName('Jane Doe')
            ->withEmail('jane.doe@example.com')
            ->assemble();
        $user3 = UserAssembler::new()
            ->withName('Bob Smith')
            ->withEmail('bob.smith@example.com')
            ->assemble();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user1);
        $entityManager->persist($user2);
        $entityManager->persist($user3);
        $entityManager->flush();

        $matchingUsers = $this->userRepository->findAllMatching('jane.doe');
        $this->assertCount(1, $matchingUsers);
        $this->assertEquals($user2, $matchingUsers[0]);

        $matchingUsers = $this->userRepository->findAllMatching('smith');
        $this->assertCount(1, $matchingUsers);
        $this->assertEquals($user3, $matchingUsers[0]);
    }
}
