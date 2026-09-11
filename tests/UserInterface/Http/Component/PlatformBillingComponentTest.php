<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Application\Service\PlatformBillingService;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\PlatformBillingComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class PlatformBillingComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private function createAdminUser(): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function createSuperAdminUser(): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_SUPER_ADMIN')->assemble();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function testCanRender(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser();
        $client->loginUser($user);

        $platformBillingService = self::getContainer()->get(PlatformBillingService::class);
        $platformBillingService->addCommissionToCurrentDue(Money::of('1000.00', 'PLN'));
        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        /** @var PlatformBillingComponent $component */
        $component = $testComponent->component();
        static::assertNotEmpty($component->getCurrentDue());
    }

    public function testCanOpenModal(): void
    {
        $client = static::createClient();
        $user = $this->createSuperAdminUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        // Open modal
        $testComponent->call('openModal');

        /** @var PlatformBillingComponent $component */
        $component = $testComponent->component();
        static::assertTrue($component->showModal);
    }

    public function testCanCloseModal(): void
    {
        $client = static::createClient();
        $user = $this->createSuperAdminUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        // Open and then close modal
        $testComponent->call('openModal');
        $testComponent->call('closeModal');

        /** @var PlatformBillingComponent $component */
        $component = $testComponent->component();
        static::assertFalse($component->showModal);
    }

    public function testCanSetPastDueAsPaid(): void
    {
        $client = static::createClient();
        $user = $this->createSuperAdminUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        // Set past due as paid
        $testComponent->call('setPastDueAsPaid');

        /** @var PlatformBillingComponent $component */
        $component = $testComponent->component();
        static::assertNotNull($component->successMessage);
    }

    public function testPlainAdminCannotOpenModal(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        $this->expectException(AccessDeniedException::class);
        $testComponent->call('openModal');
    }

    public function testPlainAdminCannotSetPastDueAsPaid(): void
    {
        $client = static::createClient();
        $user = $this->createAdminUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client);

        $this->expectException(AccessDeniedException::class);
        $testComponent->call('setPastDueAsPaid');
    }

    public function testMountDeniesAccessWithoutAdminRole(): void
    {
        $client = static::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $customer = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $entityManager->persist($customer);
        $entityManager->flush();

        $client->loginUser($customer);

        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent(name: PlatformBillingComponent::class, client: $client)->render();
    }
}
