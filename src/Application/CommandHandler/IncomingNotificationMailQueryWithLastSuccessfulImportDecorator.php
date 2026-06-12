<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Repository\SettingRepository;
use DirectoryTree\ImapEngine\MessageQueryInterface;

final readonly class IncomingNotificationMailQueryWithLastSuccessfulImportDecorator implements IncomingNotificationMailQuery
{
    public function __construct(
        private IncomingNotificationMailQuery $decorated,
        private SettingRepository $settingRepository,
    ) {}

    /**
     * @return iterable<MessageQueryInterface>
     */
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
        if (! isset($content['date']) || ! is_string($content['date'])) {
            return null;
        }

        try {
            return new \DateTimeImmutable($content['date']);
        } catch (\Exception) {
            return null;
        }
    }
}
