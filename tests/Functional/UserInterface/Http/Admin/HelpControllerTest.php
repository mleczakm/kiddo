<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Admin;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class HelpControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testIndexListsArticlesForAHost(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        $this->client->request('GET', '/admin/pomoc');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pomoc i dokumentacja');
        self::assertSelectorExists('a[href="/admin/pomoc/wprowadzenie"]');
    }

    public function testArticlePageRenders(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        $this->client->request('GET', '/admin/pomoc/wprowadzenie');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article h1', 'Wprowadzenie do panelu');
    }

    public function testContextualHelpRendersOnlyTheRequestedArticleFragment(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        $this->client->request('GET', '/admin/pomoc/rezerwacje-manualne-i-szybkie/modal');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-help-title="Rezerwacje manualne i szybkie"]');
        self::assertSelectorTextContains('.help-prose h2', 'Formularz manualny');
        self::assertSelectorExists('a[href="/admin/pomoc/rezerwacje-manualne-i-szybkie"]');
        self::assertSelectorNotExists('#root');
    }

    public function testContextualHelpStillHonoursArticleRole(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        $this->client->request('GET', '/admin/pomoc/rozliczenie-platformy/modal');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        $this->client->request('GET', '/admin/pomoc/nie-ma-takiego');

        self::assertResponseStatusCodeSame(404);
    }

    public function testArticleRequiringAHigherRoleIsHiddenFromAPlainHost(): void
    {
        $this->client->loginUser($this->user('ROLE_HOST'));
        // ustawienia-systemu requires ROLE_SETTINGS.
        $this->client->request('GET', '/admin/pomoc/ustawienia-systemu');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminSeesTheSettingsArticlesAndCriticalBackgroundIntervals(): void
    {
        $this->client->loginUser($this->user('ROLE_ADMIN'));
        $this->client->request('GET', '/admin/pomoc/ustawienia-systemu');

        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/pomoc/automatyzacje-i-zadania-w-tle');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Co 30 sekund');
        self::assertSelectorTextContains('article', 'Codziennie 08:45');
        self::assertSelectorTextContains('article', '1. dnia miesiąca 04:20');
        self::assertSelectorTextContains('article', 'Co 5 sekund');
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/pomoc');

        self::assertResponseRedirects();
    }

    private function user(string $role): User
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->withRoles($role)->assemble();
        $em->persist($user);
        $em->flush();

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $reloaded = $users->find($user->getId());
        static::assertNotNull($reloaded);

        return $reloaded;
    }
}
