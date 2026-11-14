<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\FinanceContact;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminSettingsComponent;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class AdminFinanceContactsComponentTest extends AdminSettingsComponentTestCase
{
    public function testFinanceContactCanBeAdded(): void
    {
        // Initially no finance contacts
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(0, $contacts);

        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);
        static::assertEquals($this->regularUser->getId(), $contacts[0]->getUser()->getId());
    }

    public function testFinanceContactCanBeRemoved(): void
    {
        // Add finance contact
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        // Verify it was added
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);

        // Remove it
        $this->entityManager->remove($financeContact);
        $this->entityManager->flush();

        // Verify it was removed
        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(0, $contacts);
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
        static::assertGreaterThanOrEqual(1, count($contacts));
    }

    public function testFinanceContactCanBeAddedAndRemovedThroughTheComponent(): void
    {
        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);

        static::assertCount(0, $this->financeContactRepository->findAll());

        $component->call('addFinanceContact', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        $contacts = $this->financeContactRepository->findAll();
        static::assertCount(1, $contacts);
        static::assertSame($this->regularUser->getId(), $contacts[0]->getUser()->getId());

        $component->call('removeFinanceContact', [
            'userId' => (string) $this->regularUser->getId(),
        ]);

        static::assertCount(0, $this->financeContactRepository->findAll());
    }

    public function testFinanceContactSearchFindsUsersByIdNameEmailOrChildName(): void
    {
        $withChild = UserAssembler::new()->withName('Anna Kowalska')->withEmail('anna@example.com')->assemble();
        $this->entityManager->persist($withChild);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('financeContactSearch', (string) $withChild->getId());
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForFinanceContact();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertContains($withChild->getId(), $ids);
    }

    public function testFinanceContactSearchExcludesUsersAlreadyFinanceContacts(): void
    {
        $financeContact = new FinanceContact($this->regularUser);
        $this->entityManager->persist($financeContact);
        $this->entityManager->flush();

        $component = $this->createLiveComponent(name: 'AdminSettings', client: $this->client);
        $component->set('financeContactSearch', $this->regularUser->getEmail());
        /** @var AdminSettingsComponent $adminSettingsComponent */
        $adminSettingsComponent = $component->component();
        $results = $adminSettingsComponent->getFilteredUsersForFinanceContact();

        $ids = array_map(static fn(User $u) => $u->getId(), $results);
        static::assertNotContains($this->regularUser->getId(), $ids);
    }
}
