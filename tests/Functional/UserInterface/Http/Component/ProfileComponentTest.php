<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\ProfileComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

class ProfileComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;
    private Security $security;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->security = self::getContainer()->get(Security::class);
    }

    private function loginUser(string $email, array $roles = ['ROLE_USER']): User
    {
        $user = UserAssembler::new()->assemble();
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = new PostAuthenticationToken(
            $user,
            'main',
            $user->getRoles()
        );
        
        $this->security->getTokenStorage()->setToken($token);
        
        return $user;
    }

    public function testCanRender(): void
    {
        $this->loginUser('test@example.com');
        $testComponent = $this->createLiveComponent(name: ProfileComponent::class);
        $this->assertStringContainsString('div', (string) $testComponent->render());
    }

    public function testAccountTypeDisplay(): void
    {
        // Test with admin role
        $adminUser = $this->loginUser('admin@example.com', ['ROLE_ADMIN']);
        $testComponent = $this->createLiveComponent(name: ProfileComponent::class);
        
        $output = (string) $testComponent->render();
        $this->assertStringContainsString('Administrator', $output);
        
        // Test with regular user role
        $regularUser = $this->loginUser('user@example.com', ['ROLE_USER']);
        $testComponent = $this->createLiveComponent(name: ProfileComponent::class);
        
        $output = (string) $testComponent->render();
        $this->assertStringContainsString('User', $output);
    }
}
