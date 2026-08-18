<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\ProfileComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class ProfileComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void {}

    private function createUser(string ...$roles): User
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withRoles(...$roles)->assemble();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createSubscribedUser(): User
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withRoles('ROLE_USER')->withNewsletterSubscribed(true)->assemble();

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
        static::assertStringContainsString('Informacje o użytkowniku', $rendered);
        static::assertStringContainsString($user->getEmail(), $rendered);
    }

    public function testAccountTypeDisplay(): void
    {
        $client = static::createClient();
        $adminUser = $this->createUser('ROLE_ADMIN');
        $client->loginUser($adminUser);
        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);

        $output = (string) $testComponent->render();
        static::assertStringContainsString('Administrator', $output);
    }

    public function testCanEditProfile(): void
    {
        $client = static::createClient();
        $user = $this->createUser('ROLE_USER');
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);

        // Start editing
        $testComponent->call('startEditing');
        /** @var ProfileComponent $profileComponent */
        $profileComponent = $testComponent->component();
        static::assertTrue($profileComponent->isEditing);
        static::assertStringContainsString(
            '<form data-action="live#action" data-live-action-param="save">',
            (string) $testComponent->render(),
        );

        // Set new values (phone is required on profile save)
        $testComponent->set('name', 'New Name')->set('email', 'new.email@example.com')->set('phone', '500 600 700');

        // Save changes
        $testComponent->call('save');

        /** @var ProfileComponent $profileComponent */
        $profileComponent = $testComponent->component();
        static::assertFalse($profileComponent->isEditing);

        // Check if user is updated in the database
        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        static::assertSame('New Name', $updatedUser->getName());
        static::assertSame('new.email@example.com', $updatedUser->getEmail());
        static::assertNotNull($updatedUser->getPhone());

        // Check if the view is updated
        $rendered = (string) $testComponent->render();
        static::assertStringContainsString('New Name', $rendered);
        static::assertStringContainsString('new.email@example.com', $rendered);
        static::assertStringContainsString('500 600 700', $rendered);
    }

    public function testEnablingNewsletterPersistsSubscription(): void
    {
        $client = static::createClient();
        $user = $this->createUser('ROLE_USER');
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);
        $testComponent->call('startEditing');

        $testComponent->set('name', $user->getName())->set('email', $user->getEmail())->set(
            'phone',
            '500 600 700',
        )->set('newsletterSubscribed', true);

        $testComponent->call('save');

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        static::assertTrue($updatedUser->isNewsletterSubscribed());
        static::assertNotNull($updatedUser->getNewsletterConsentDate());
    }

    public function testDisablingNewsletterClearsSubscription(): void
    {
        $client = static::createClient();
        $user = $this->createSubscribedUser();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);
        $testComponent->call('startEditing');

        $testComponent->set('name', $user->getName())->set('email', $user->getEmail())->set(
            'phone',
            '500 600 700',
        )->set('newsletterSubscribed', false);

        $testComponent->call('save');

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        static::assertFalse($updatedUser->isNewsletterSubscribed());
        static::assertNull($updatedUser->getNewsletterConsentDate());
    }

    public function testUnchangedNewsletterStateIsPreserved(): void
    {
        $client = static::createClient();
        $user = $this->createSubscribedUser();
        $originalConsent = $user->getNewsletterConsentDate();
        $client->loginUser($user);

        $testComponent = $this->createLiveComponent(name: ProfileComponent::class, client: $client);
        $testComponent->call('startEditing');

        $testComponent->set('name', 'Renamed User')->set('email', $user->getEmail())->set('phone', '500 600 700')->set(
            'newsletterSubscribed',
            true,
        );

        $testComponent->call('save');

        /** @var User $updatedUser */
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        static::assertSame('Renamed User', $updatedUser->getName());
        static::assertTrue($updatedUser->isNewsletterSubscribed());
        // Consent date was not overwritten: still within 1s of the original.
        static::assertNotNull($updatedUser->getNewsletterConsentDate());
        static::assertNotNull($originalConsent);
        static::assertEqualsWithDelta(
            $originalConsent->getTimestamp(),
            $updatedUser->getNewsletterConsentDate()->getTimestamp(),
            1.0,
        );
    }
}
