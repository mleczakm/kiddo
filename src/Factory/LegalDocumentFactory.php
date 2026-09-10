<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<LegalDocument> */
final class LegalDocumentFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return LegalDocument::class;
    }

    /** @return array<string, mixed> */
    #[\Override]
    protected function defaults(): array
    {
        return [
            'type' => LegalDocumentType::APP_TERMS,
        ];
    }

    public function privacy(): self
    {
        return $this->with(['type' => LegalDocumentType::PRIVACY]);
    }

    public function classesTerms(): self
    {
        return $this->with(['type' => LegalDocumentType::CLASSES_TERMS_GENERAL]);
    }
}
