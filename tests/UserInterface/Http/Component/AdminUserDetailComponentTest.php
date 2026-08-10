<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ActivityLog;
use App\Entity\Child;
use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminUserDetailComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminUserDetailComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testEditingTheAdminNoteSavesItAndRecordsHistory(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->withName('Bartosz Nowak')->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(
            name: AdminUserDetailComponent::class,
            data: [
                'userId' => $customer->getId(),
            ],
            client: $this->client,
        );

        $component->call('startEditField', [
            'field' => 'adminNote',
        ]);
        $component->set('fieldValue', 'Preferuje kontakt telefoniczny po 16:00.');
        // Both the debounced "input" auto-save and the "blur" save call the same
        // saveField action with no arguments - it always commits and exits.
        $component->call('saveField');

        /** @var AdminUserDetailComponent $state */
        $state = $component->component();
        self::assertNull($state->editingField, 'saving must return to display mode');

        $reloaded = $this->em->getRepository(User::class)->find($customer->getId()) ?? throw new \LogicException(
            'User not found'
        );
        self::assertSame('Preferuje kontakt telefoniczny po 16:00.', $reloaded->getAdminNote());

        $logs = $this->em->getRepository(ActivityLog::class)->findBySubject($reloaded);
        self::assertCount(1, $logs);
        self::assertStringContainsString('Notatkę administracyjną', (string) $logs[0]->getSummary());
    }

    public function testEditingNameValidatesAndRejectsBlank(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->withName('Bartosz Nowak')->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(
            name: AdminUserDetailComponent::class,
            data: [
                'userId' => $customer->getId(),
            ],
            client: $this->client,
        );

        $component->call('startEditField', [
            'field' => 'name',
        ]);
        $component->set('fieldValue', '   ');
        $component->call('saveField', []);

        /** @var AdminUserDetailComponent $state */
        $state = $component->component();
        self::assertNotNull($state->fieldError);
        self::assertSame('Bartosz Nowak', $customer->getName(), 'blank name must not be persisted');
    }

    public function testSendNotificationFromTheUserPage(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->withName('Bartosz Nowak')->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(
            name: AdminUserDetailComponent::class,
            data: [
                'userId' => $customer->getId(),
            ],
            client: $this->client,
        );

        $component->call('startCompose');
        $component->set('notifyTitle', 'Witamy!');
        $component->set('notifyBody', 'Twoje konto jest gotowe.');
        $component->call('sendNotification');

        $notifications = $this->em->getRepository(Notification::class)->findBy([
            'user' => $customer,
        ]);
        self::assertCount(1, $notifications);
        self::assertSame('Witamy!', $notifications[0]->getTitle());

        /** @var AdminUserDetailComponent $state */
        $state = $component->component();
        self::assertFalse($state->composingNotification);
    }

    public function testAddingAChildPersistsItAndRecordsHistory(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->withName('Bartosz Nowak')->assemble();
        $this->em->persist($customer);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(
            name: AdminUserDetailComponent::class,
            data: [
                'userId' => $customer->getId(),
            ],
            client: $this->client,
        );

        $component->call('startAddChild');
        $component->set('newChildName', 'Maja Nowak');
        $component->set('newChildBirthday', '2023-12-10');
        $component->call('saveNewChild');

        $children = $this->em->getRepository(Child::class)->findByOwner($customer);
        self::assertCount(1, $children);
        self::assertSame('Maja Nowak', $children[0]->getName());

        $logs = $this->em->getRepository(ActivityLog::class)->findBySubject($customer);
        self::assertCount(1, $logs);
        self::assertStringContainsString('Maja Nowak', $logs[0]->getTitle());
    }
}
