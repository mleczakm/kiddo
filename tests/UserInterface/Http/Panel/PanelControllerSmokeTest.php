<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Panel;

use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
final class PanelControllerSmokeTest extends WebTestCase
{
    private const ROUTES = [
        '/panel',
        '/panel/rezerwacje',
        '/panel/zajecia',
        '/panel/rozliczenia',
        '/panel/kup',
        '/profil',
    ];

    public function testPanelRoutesAreProtectedFromAnonymous(): void
    {
        $client = static::createClient();

        foreach (self::ROUTES as $route) {
            $client->request('GET', $route);
            $status = $client->getResponse()->getStatusCode();
            static::assertContains(
                $status,
                [301, 302, 401, 403],
                "Route {$route} must not be reachable anonymously (got {$status})",
            );
        }
    }

    public function testPanelRoutesRenderForAuthenticatedUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser($client));

        foreach (self::ROUTES as $route) {
            $client->request('GET', $route);
            $this->assertResponseIsSuccessful("Route {$route} should render for a logged-in user");
        }
    }

    public function testOverviewShowsPanelNavigation(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser($client));

        $client->request('GET', '/panel');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/panel/rezerwacje"]');
        $this->assertSelectorExists('a[href="/panel/zajecia"]');
        $this->assertSelectorExists('a[href="/panel/rozliczenia"]');
        $this->assertSelectorExists('a[href="/panel/kup"]');
    }

    public function testPersonalPageShowsProfileChildrenAndDocuments(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser($client));

        $client->request('GET', '/profil');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        static::assertStringContainsString('Informacje o użytkowniku', $content);
        static::assertStringContainsString('Moje dzieci', $content);
        static::assertStringContainsString('Zgody i dokumenty', $content);
    }

    private function createUser(KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()
            ->withEmail('panel-user@test.com')
            ->withName('Panel User')
            ->withRoles('ROLE_USER')
            ->assemble();

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
