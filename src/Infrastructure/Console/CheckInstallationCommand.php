<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Repository\SettingRepositoryInterface;
use App\Application\Service\OrganizationDetailsProvider;
use App\Application\Service\Payment\TransferReviewThresholdProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installation guard: every Setting the running app reads at runtime must be
 * present and complete in the `setting` table.
 *
 * A fresh deployment against an empty `setting` table looks healthy - the ORM
 * connects, /health is green - while {@see OrganizationDetailsProvider} and
 * {@see TransferReviewThresholdProvider} silently substitute historical
 * hard-coded values that are wrong for any other organization: the customer-
 * facing BLIK / bank-transfer payment instructions (chat, e-mail, panel) then
 * point at the wrong account.
 *
 * The Ansible deploy runs this against the new image *before* it cuts traffic
 * to the new container, so a missing setting fails the release with a precise
 * list for the super administrator instead of shipping wrong payment details.
 *
 * @phpstan-type FieldRule 'string'|'numeric'
 */
#[AsCommand(
    name: 'app:check-installation',
    description: 'Verify every runtime Setting is configured; fails if any is missing or incomplete',
)]
final class CheckInstallationCommand extends Command
{
    /**
     * setting key => [critical, fields => (field => rule)].
     *
     * `critical` = a wrong/defaulted value has customer-facing or financial
     * impact; `recommended` = internal tuning that is safe to leave at its
     * default. Every listed field must still be present for a clean install.
     * Keep this in sync with the providers that read each key.
     *
     * @var array<string, array{critical: bool, fields: array<string, FieldRule>}>
     */
    private const array REQUIRED_SETTINGS = [
        // App\Application\Service\OrganizationDetailsProvider - falls back to DEFAULTS.
        // Feeds the BLIK phone + bank account printed on every payment instruction.
        OrganizationDetailsProvider::SETTING_KEY => [
            'critical' => true,
            'fields' => [
                'name' => 'string',
                'street' => 'string',
                'postal_code' => 'string',
                'city' => 'string',
                'email' => 'string',
                'phone' => 'string',
                'bank_account' => 'string',
                'blik_phone' => 'string',
            ],
        ],
        // App\Application\Service\Payment\TransferReviewThresholdProvider - falls back to 1000 PLN.
        TransferReviewThresholdProvider::SETTING_KEY => [
            'critical' => false,
            'fields' => [
                'amount_pln' => 'numeric',
            ],
        ],
    ];

    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation check: required settings');

        $rows = [];
        /** @var list<string> $missingCritical */
        $missingCritical = [];
        /** @var list<string> $missingRecommended */
        $missingRecommended = [];

        foreach (self::REQUIRED_SETTINGS as $key => $spec) {
            $content = $this->settingRepository->findOneByKey($key)?->getContent();
            $exists = is_array($content);

            foreach ($spec['fields'] as $field => $rule) {
                $ok = $exists && $this->valueSatisfies($content[$field] ?? null, $rule);
                $rows[] = [
                    $key,
                    $field,
                    $spec['critical'] ? 'critical' : 'recommended',
                    $ok ? '<fg=green>OK</>' : '<fg=red>MISSING</>',
                ];

                if ($ok) {
                    continue;
                }

                if ($spec['critical']) {
                    $missingCritical[] = sprintf('%s.%s', $key, $field);

                    continue;
                }

                $missingRecommended[] = sprintf('%s.%s', $key, $field);
            }
        }

        $io->table(['Setting', 'Field', 'Severity', 'Status'], $rows);

        if ($missingCritical === [] && $missingRecommended === []) {
            $io->success('All required settings are configured.');

            return Command::SUCCESS;
        }

        if ($missingCritical !== []) {
            $io->error(
                'Critical setting(s) not configured - customer-facing payment details are falling back to built-in defaults:'
                    . "\n  - "
                    . implode("\n  - ", $missingCritical),
            );
        }

        if ($missingRecommended !== []) {
            $io->warning(
                'Setting(s) not configured - the app is falling back to built-in defaults:' . "\n  - "
                    . implode("\n  - ", $missingRecommended),
            );
        }

        $io->note(
            'A super administrator must configure these under Admin -> Settings '
            . '(organization details, transfer review threshold), which persists them '
            . 'to the `setting` table.',
        );

        return Command::FAILURE;
    }

    /** @param FieldRule $rule */
    private function valueSatisfies(mixed $value, string $rule): bool
    {
        return match ($rule) {
            'string' => is_string($value) && trim($value) !== '',
            'numeric' => is_numeric($value),
        };
    }
}
