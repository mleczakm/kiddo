<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ConsentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AccountSettingsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AccountSettingsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testDeleteDoesNothingUntilTheKeywordIsTyped(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $component = $this->createLiveComponent(name: AccountSettingsComponent::class, client: $client);
        $component->set('deleteConfirmation', 'usun');
        $component->call('delete');

        $user = $this->reload($user);
        static::assertNull($user->getLifecycle()->deletionRequestedAt());
    }

    public function testTypingTheKeywordStartsTheDeletionGraceAndRecordsConsent(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $component = $this->createLiveComponent(name: AccountSettingsComponent::class, client: $client);
        $component->set('deleteConfirmation', 'USUŃ');
        $component->call('delete');

        $user = $this->reload($user);
        static::assertNotNull($user->getLifecycle()->deletionRequestedAt());
        static::assertNotNull($user->getLifecycle()->deactivatedAt());

        /** @var UserConsentRepository $consents */
        $consents = self::getContainer()->get(UserConsentRepository::class);
        static::assertNotNull($consents->findLatestActive($user, ConsentType::ACCOUNT_DELETION));
    }

    public function testPauseDeactivatesTheAccount(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $this->createLiveComponent(name: AccountSettingsComponent::class, client: $client)->call('pause');

        $user = $this->reload($user);
        static::assertNotNull($user->getLifecycle()->deactivatedAt());
        static::assertNull($user->getLifecycle()->deletionRequestedAt());
    }

    private function createUser(): User
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function reload(User $user): User
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $reloaded = $users->find($user->getId());
        static::assertNotNull($reloaded);

        return $reloaded;
    }
}
