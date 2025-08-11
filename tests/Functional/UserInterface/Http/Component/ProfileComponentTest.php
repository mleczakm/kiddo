<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\ProfileComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class ProfileComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void {}

    private function createUser(string ... $roles): User
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withRoles(... $roles)->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function testCanRender(): void
    {
        $client = static::createClient();
        $user = $this->createUser('ROLE_USER');
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('Informacje o użytkowniku', $rendered);
        $this->assertStringContainsString($user->getEmail(), $rendered);
    }

    public function testAccountTypeDisplay(): void
    {
        $client = static::createClient();
        $adminUser = $this->createUser('ROLE_ADMIN');
        $client->loginUser($adminUser);
        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);

        $output = (string) $testComponent->render();
        $this->assertStringContainsString('Administrator', $output);
    }
}
