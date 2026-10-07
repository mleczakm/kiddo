<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\StaffNotificationPreferencesComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class StaffNotificationPreferencesComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface, 'test container provides the ORM entity manager');
        $this->em = $em;
    }

    public function testAdminCanOptOutAndBackIn(): void
    {
        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(
            name: StaffNotificationPreferencesComponent::class,
            client: $this->client,
        );
        static::assertStringContainsString('checked', (string) $component->render());

        $html = (string) $component->call('toggle')->render();
        static::assertStringNotContainsString('checked', $html);
        static::assertFalse($this->reload($admin)->receivesOperationalEmails());

        $component->call('toggle');
        static::assertTrue($this->reload($admin)->receivesOperationalEmails());
    }

    public function testToggleIsRefusedForNonAdmins(): void
    {
        $host = UserAssembler::new()->withEmail('host@example.com')->withRoles('ROLE_HOST')->assemble();
        $this->em->persist($host);
        $this->em->flush();
        $this->client->loginUser($host);

        $component = $this->createLiveComponent(
            name: StaffNotificationPreferencesComponent::class,
            client: $this->client,
        );

        $this->expectException(AccessDeniedException::class);
        $component->call('toggle');
    }

    private function reload(User $user): User
    {
        $this->em->clear();
        $reloaded = $this->em->find(User::class, $user->getId());
        static::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }
}
