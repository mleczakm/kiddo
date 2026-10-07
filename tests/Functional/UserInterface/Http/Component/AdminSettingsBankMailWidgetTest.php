<?php

declare(strict_types=1);

namespace App\Tests\Functional\UserInterface\Http\Component;

use App\Entity\User;
use App\UserInterface\Http\Component\AdminSettingsComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminSettingsBankMailWidgetTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testFinancialSettingsShowsTheConfiguredRecipientAndHelpArticle(): void
    {
        $name = 'BANK_MAIL_ADDRESS';
        $address = '0123456789abcdef0123456789abcdef@warsztatowniasensoryczna.pl';
        $previousEnvironment = getenv($name);
        $previousServer = $_SERVER[$name] ?? null;
        $previousEnv = $_ENV[$name] ?? null;
        putenv($name . '=' . $address);
        $_SERVER[$name] = $address;
        $_ENV[$name] = $address;

        try {
            $client = static::createClient();
            $admin = $this->persistAdmin();
            $client->loginUser($admin);

            $component = $this->createLiveComponent(AdminSettingsComponent::class, client: $client);
            $component->set('settingsTab', 'payments');
            $html = $component->render()->toString();

            static::assertStringContainsString($address, $html);
            static::assertStringContainsString('/admin/pomoc/powiadomienia-e-mail-o-przelewach', $html);
            static::assertStringContainsString('Wpisz ten adres w ustawieniach powiadomień banku', $html);
        } finally {
            static::ensureKernelShutdown();
            $previousEnvironment === false ? putenv($name) : putenv($name . '=' . $previousEnvironment);
            self::restoreEnvValue($_SERVER, $name, $previousServer);
            self::restoreEnvValue($_ENV, $name, $previousEnv);
        }
    }

    public function testHelpArticleExplainsTheAliorNotificationAddress(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistAdmin());
        $client->request('GET', '/admin/pomoc/powiadomienia-e-mail-o-przelewach');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('article', 'Ustawienia → Przelewy i płatności');
        static::assertSelectorTextContains('article', 'Gmail jest nadal sprawdzany przez okres migracji');
    }

    /** @param array<string, mixed> $environment */
    private static function restoreEnvValue(array &$environment, string $name, mixed $value): void
    {
        if ($value === null) {
            unset($environment[$name]);

            return;
        }

        $environment[$name] = $value;
    }

    private function persistAdmin(): User
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = new User('bank-settings-admin-' . bin2hex(random_bytes(4)) . '@example.test', 'Bank Settings Admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $entityManager->persist($admin);
        $entityManager->flush();

        return $admin;
    }
}
