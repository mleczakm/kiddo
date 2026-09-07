<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Console;

use App\Application\Repository\SettingRepositoryInterface;
use App\Application\Service\OrganizationDetailsProvider;
use App\Application\Service\Payment\TransferReviewThresholdProvider;
use App\Entity\Setting;
use App\Infrastructure\Console\CheckInstallationCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
final class CheckInstallationCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(CheckInstallationCommand::class);
        self::assertInstanceOf(CheckInstallationCommand::class, $command);
        $this->commandTester = new CommandTester($command);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;

        // Known baseline: the DAMA transaction is rolled back after the test,
        // so clearing these keys here only affects this test. `key` is a DQL
        // reserved word, so go through the repository rather than a DELETE query.
        /** @var SettingRepositoryInterface $settings */
        $settings = self::getContainer()->get(SettingRepositoryInterface::class);
        foreach ([OrganizationDetailsProvider::SETTING_KEY, TransferReviewThresholdProvider::SETTING_KEY] as $key) {
            $existing = $settings->findOneByKey($key);
            if ($existing !== null) {
                $this->em->remove($existing);
            }
        }
        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @param array<string, mixed> $content
     */
    private function seedSetting(string $key, array $content): void
    {
        $setting = new Setting();
        $setting->setKey($key);
        $setting->setContent($content);
        $this->em->persist($setting);
        $this->em->flush();
    }

    private function seedOrganizationDetails(): void
    {
        $this->seedSetting(OrganizationDetailsProvider::SETTING_KEY, [
            'name' => 'Test Org',
            'street' => 'Testowa 1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'email' => 'org@example.test',
            'phone' => '+48 500 000 000',
            'bank_account' => '00 1111 2222 3333 4444 5555 6666',
            'blik_phone' => '500 000 000',
        ]);
    }

    private function seedTransferReviewThreshold(): void
    {
        $this->seedSetting(TransferReviewThresholdProvider::SETTING_KEY, [
            'amount_pln' => 1500,
        ]);
    }

    public function testPassesWhenEveryRequiredSettingIsConfigured(): void
    {
        $this->seedOrganizationDetails();
        $this->seedTransferReviewThreshold();

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('All required settings are configured.', $this->commandTester->getDisplay());
    }

    public function testFailsAndListsTheMissingCriticalOrganizationDetails(): void
    {
        $this->seedTransferReviewThreshold();

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));

        // SymfonyStyle word-wraps the [ERROR] block, so assert on the stable
        // dotted tokens from the bullet list rather than the prose headline.
        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('[ERROR]', $display);
        static::assertStringContainsString(OrganizationDetailsProvider::SETTING_KEY . '.bank_account', $display);
        static::assertStringContainsString(OrganizationDetailsProvider::SETTING_KEY . '.blik_phone', $display);
    }

    public function testFailsWhenOnlyTheRecommendedThresholdIsMissing(): void
    {
        $this->seedOrganizationDetails();

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString('[WARNING]', $display);
        static::assertStringContainsString(TransferReviewThresholdProvider::SETTING_KEY . '.amount_pln', $display);
    }

    public function testTreatsABlankOrNonNumericValueAsMissing(): void
    {
        $this->seedSetting(OrganizationDetailsProvider::SETTING_KEY, [
            'name' => 'Test Org',
            'street' => 'Testowa 1',
            'postal_code' => '00-001',
            'city' => 'Warszawa',
            'email' => 'org@example.test',
            'phone' => '+48 500 000 000',
            'bank_account' => '00 1111 2222 3333 4444 5555 6666',
            'blik_phone' => '   ',
        ]);
        $this->seedSetting(TransferReviewThresholdProvider::SETTING_KEY, [
            'amount_pln' => 'not-a-number',
        ]);

        static::assertSame(Command::FAILURE, $this->commandTester->execute([]));

        $display = $this->commandTester->getDisplay();
        static::assertStringContainsString(OrganizationDetailsProvider::SETTING_KEY . '.blik_phone', $display);
        static::assertStringContainsString(TransferReviewThresholdProvider::SETTING_KEY . '.amount_pln', $display);
    }
}
