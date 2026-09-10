<?php

declare(strict_types=1);

namespace App\Factory;

use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\RequestContext;
use App\Entity\ConsentSource;
use App\Entity\ConsentType;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\UserConsent;
use Symfony\Component\HttpFoundation\RequestStack;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<UserConsent> */
final class UserConsentFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return UserConsent::class;
    }

    /**
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     */
    #[\Override]
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'type' => ConsentType::MARKETING_EMAIL,
            'source' => ConsentSource::REGISTRATION,
            'evidence' => ConsentEvidence::statement('I agree to receive marketing emails.'),
            'requestContext' => new RequestContext(new RequestStack()),
        ];
    }

    /** @throws \InvalidArgumentException */
    public function forDocument(LegalDocumentVersion $version, string $acceptanceText): self
    {
        return $this->with([
            'type' => match ($version->getDocument()->getType()) {
                LegalDocumentType::APP_TERMS => ConsentType::APP_TERMS,
                LegalDocumentType::PRIVACY => ConsentType::PRIVACY,
                LegalDocumentType::CLASSES_TERMS_GENERAL => ConsentType::CLASSES_TERMS,
            },
            'evidence' => ConsentEvidence::versionedDocument($version, $acceptanceText),
        ]);
    }
}
