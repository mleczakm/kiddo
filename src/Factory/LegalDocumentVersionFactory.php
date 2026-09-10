<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LegalDocumentVersion;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<LegalDocumentVersion> */
final class LegalDocumentVersionFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return LegalDocumentVersion::class;
    }

    /** @return array<string, mixed> */
    #[\Override]
    protected function defaults(): array
    {
        return [
            'document' => LegalDocumentFactory::new(),
            'file' => FileFactory::new(),
            'effectiveFrom' => new \DateTimeImmutable('-1 day'),
            'publishedBy' => UserFactory::new(),
            'changeSummary' => self::faker()->sentence(),
        ];
    }
}
