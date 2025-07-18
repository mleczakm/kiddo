<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Mailer\Test\InteractsWithMailer;

class SecurityControllerTest extends WebTestCase
{
    use InteractsWithMailer;

    private const string LOGIN_URL = '/login';
    private const string TEST_EMAIL = 'test@example.com';

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testLoginPageLoadsSuccessfully(): void
    {
        $this->client->request(Request::METHOD_GET, self::LOGIN_URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="form"]');
        $this->assertSelectorExists('input[type="email"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testLoginWithValidEmailShowsConfirmation(): void
    {
        $this->markTestSkipped('Test uses JS, need to be transformed to use InteractsWithLiveComponents');
        $user = UserAssembler::new()->withEmail(self::TEST_EMAIL)->assemble();
        $this->em->persist($user);
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, self::LOGIN_URL);

        // Find the form and submit it with a valid email
        $form = $crawler->selectButton('form[submit]')
            ->form([
                'form[email]' => self::TEST_EMAIL,
            ]);

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();
        // Check for any h1 element since the translation key might be different
        $this->assertSelectorExists('h1');

        $emails = $this->mailer()
            ->sentEmails();
        $userEmail = $emails->whereTo(self::TEST_EMAIL);

        $this->assertStringContainsString('Zaloguj się', (string) $userEmail->first()->getHtmlBody());
    }

    public function testLoginWithMissingEmailRedirectsToConfirmationAndSendEmailAboutIt(): void
    {
        $this->markTestSkipped('Test uses JS, need to be transformed to use InteractsWithLiveComponents');

        $crawler = $this->client->request(Request::METHOD_GET, self::LOGIN_URL);

        // Find the form and submit it with a valid email
        $form = $crawler->selectButton('form[submit]')
            ->form([
                'form[email]' => self::TEST_EMAIL,
            ]);

        $this->client->submit($form);

        $this->assertResponseIsSuccessful();

        // Check for any h1 element since the translation key might be different
        $this->assertSelectorExists('h1');

        $emails = $this->mailer()
            ->sentEmails();
        $userEmail = $emails->whereTo(self::TEST_EMAIL);

        $this->assertStringContainsString('Nie znaleziono konta', (string) $userEmail->first()->getSubject());
    }
}
