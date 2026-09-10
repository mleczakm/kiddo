<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\File;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<File> */
final class FileFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return File::class;
    }

    /** @return array<string, mixed> */
    #[\Override]
    protected function defaults(): array
    {
        $contents = self::faker()->text();

        return [
            'originalName' => self::faker()->unique()->slug() . '.pdf',
            'mimeType' => 'application/pdf',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'data' => base64_encode($contents),
        ];
    }
}
