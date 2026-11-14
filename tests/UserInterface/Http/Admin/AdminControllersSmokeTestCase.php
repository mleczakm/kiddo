<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AdminControllersSmokeTestCase extends WebTestCase
{
    protected function createAdminUser(KernelBrowser $client): User
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

    protected function createHostUser(KernelBrowser $client): User
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

    protected function createRegularUser(KernelBrowser $client): User
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
