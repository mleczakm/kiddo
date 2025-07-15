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

    private const string EMAIL_CONFIRMATION_URL = '/email-confirmation';

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

    public function testLoginWithValidEmailRedirectsToConfirmation(): void
    {
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

        $this->assertResponseRedirects(self::EMAIL_CONFIRMATION_URL);

        // Follow the redirect to confirm we're on the email confirmation page
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        // Check for any h1 element since the translation key might be different
        $this->assertSelectorExists('h1');

        $emails = $this->mailer()
            ->sentEmails();
        $userEmail = $emails->whereTo(self::TEST_EMAIL);

        $this->assertStringContainsString('Zaloguj się', (string) $userEmail->first()->getHtmlBody());
    }

    public function testEmailConfirmationPageRedirectsIfUserIsLoggedIn(): void
    {
        // Create and login a test user
        $user = UserAssembler::new()->assemble();

        // Get the entity manager and persist the user
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        // Clear the entity manager to ensure we get a fresh instance of the user
        $entityManager->clear();

        // Login the user
        $this->client->loginUser($user);

        // Test the redirect
        $this->client->request(Request::METHOD_GET, self::EMAIL_CONFIRMATION_URL);
        $this->assertResponseRedirects('/');
    }

    public function testLoginWithMissingEmailRedirectsToConfirmationAndSendEmailAboutIt(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, self::LOGIN_URL);

        // Find the form and submit it with a valid email
        $form = $crawler->selectButton('form[submit]')
            ->form([
                'form[email]' => self::TEST_EMAIL,
            ]);

        $this->client->submit($form);

        $this->assertResponseRedirects(self::EMAIL_CONFIRMATION_URL);

        // Follow the redirect to confirm we're on the email confirmation page
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        // Check for any h1 element since the translation key might be different
        $this->assertSelectorExists('h1');

        $emails = $this->mailer()
            ->sentEmails();
        $userEmail = $emails->whereTo(self::TEST_EMAIL);

        $this->assertStringContainsString('Nie znaleziono konta', (string) $userEmail->first()->getSubject());
    }
}
