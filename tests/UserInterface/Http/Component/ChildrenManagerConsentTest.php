<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\ChildRepository;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\ChildrenManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class ChildrenManagerConsentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testAddingAChildWithoutTheGuardianDeclarationIsRejected(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $component = $this->createLiveComponent(name: ChildrenManager::class, client: $client);
        static::assertStringContainsString('data-model="guardianConfirmed"', $component->render()->toString());

        $component->set('childName', 'Zosia');
        $component->call('addChild');

        static::assertSame([], $this->childRepository()->findByOwner($user));
        static::assertSame([], $this->consentRepository()->findHistoryForUser($user));
    }

    public function testAddingAChildWithTheGuardianDeclarationRecordsConsent(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $component = $this->createLiveComponent(name: ChildrenManager::class, client: $client);
        $component->set('childName', 'Zosia');
        $component->set('guardianConfirmed', true);
        $component->call('addChild');

        $children = $this->childRepository()->findByOwner($user);
        static::assertCount(1, $children);

        $consents = $this->consentRepository()->findHistoryForUser($user);
        static::assertCount(1, $consents);
        static::assertSame(ConsentType::CHILD_DATA_GUARDIAN, $consents[0]->getType());
        static::assertSame(ConsentSource::CHILD_FORM, $consents[0]->getSource());
        static::assertSame('child:' . $children[0]->getId(), $consents[0]->getContext());
    }

    private function createUser(): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles('ROLE_USER')->assemble();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function childRepository(): ChildRepository
    {
        /** @var ChildRepository */
        return self::getContainer()->get(ChildRepository::class);
    }

    private function consentRepository(): UserConsentRepository
    {
        /** @var UserConsentRepository */
        return self::getContainer()->get(UserConsentRepository::class);
    }
}
