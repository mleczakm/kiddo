<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Repository\SettingRepositoryInterface;
use DirectoryTree\ImapEngine\MessageInterface;

final readonly class IncomingNotificationMailQueryWithLastSuccessfulImportDecorator implements
    IncomingNotificationMailQuery
{
    public function __construct(
        private IncomingNotificationMailQuery $decorated,
        private SettingRepositoryInterface $settingRepository,
    ) {}

    /**
     * @return iterable<MessageInterface>
     */
    #[\Override]
    public function __invoke(): iterable
    {
        yield from ($this->decorated)();
    }

    public function getLastSuccessfulImportDate(): ?\DateTimeImmutable
    {
        $setting = $this->settingRepository->findOneByKey('last_successful_transfer_import');
        if ($setting === null) {
            return null;
        }

        $content = $setting->getContent();
        if (!is_string($content['date'] ?? null)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($content['date']);
        } catch (\Exception) {
            return null;
        }
    }
}
