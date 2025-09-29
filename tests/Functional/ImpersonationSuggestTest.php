<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\UserInterface\Http\Component\NotificationTrayLiveComponent;
use App\Tests\Assembler\TenantAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Group('functional')]
final class ImpersonationSuggestTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    public function testAdminCanGetSuggestions(): void
    {
        $tenant = TenantAssembler::new()
            ->withName('Kiddo')
            ->withDomain('t1.test')
            ->assemble();
        $this->em->persist($tenant);

        $admin = UserAssembler::new()
            ->withEmail('admin@example.com')
            ->withName('Admin One')
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $this->em->persist($admin);

        $u1 = UserAssembler::new()->withEmail('alice@example.com')->withName('Alice')->assemble();
        $u2 = UserAssembler::new()->withEmail('bob@example.com')->withName('Bob')->assemble();
        $u3 = UserAssembler::new()->withEmail('carol@example.com')->withName('Carol')->assemble();
        $this->em->persist($u1);
        $this->em->persist($u2);
        $this->em->persist($u3);
        $this->em->flush();

        $this->setHost('t1.test');
        $this->client->loginUser($admin);

        $lc = $this->createLiveComponent(NotificationTrayLiveComponent::class, client: $this->client);
        $lc->set('query', 'al')
            ->call('suggest');
        $html = $lc->render()
            ->toString();
        self::assertStringContainsString('alice@example.com', $html);
    }

    private function setHost(string $host): void
    {
        /** @var RequestStack $rs */
        $rs = self::getContainer()->get(RequestStack::class);
        $rs->push(Request::create('https://' . $host . '/'));
    }

    public function testNonAdminHasNoSuggestions(): void
    {
        $tenant = TenantAssembler::new()
            ->withName('Kiddo')
            ->withDomain('t2.test')
            ->assemble();
        $this->em->persist($tenant);

        $user = UserAssembler::new()
            ->withEmail('user2@example.com')
            ->withName('User Two')
            ->assemble();
        $this->em->persist($user);
        $this->em->flush();

        $this->setHost('t2.test');
        $this->client->loginUser($user);

        $lc = $this->createLiveComponent(NotificationTrayLiveComponent::class, client: $this->client);
        $lc->set('query', 'us')
            ->call('suggest');
        $html = $lc->render()
            ->toString();
        // For non-admins, component should not expose any suggestion entries
        self::assertStringNotContainsString('id="impersonate-suggestions"', $html);
    }

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }
}
