<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;

final readonly class ConsentEvidence
{
    /**
     * @param LegalDocumentType|LegalDocumentVersion|null $document
     * @throws \InvalidArgumentException
     */
    private function __construct(
        private LegalDocumentType|LegalDocumentVersion|null $document,
        private ?string $documentRef,
        private ?string $documentChecksum,
        private string $textChecksum,
        private ?string $context,
    ) {
        ConsentEvidenceValue::assertChecksum($this->textChecksum, 'Consent text');
        if ($this->documentChecksum !== null) {
            ConsentEvidenceValue::assertChecksum($this->documentChecksum, 'Document');
        }
        $hasInvalidExternalEvidence =
            $this->documentRef !== null && ($this->documentChecksum === null || $this->document !== null);
        $hasOrphanedChecksum =
            $this->document === null && $this->documentRef === null && $this->documentChecksum !== null;
        if ($hasInvalidExternalEvidence || $hasOrphanedChecksum) {
            throw new \InvalidArgumentException(
                'An external document reference and checksum must be provided together.',
            );
        }
    }

    /** @throws \InvalidArgumentException */
    public static function currentDocument(
        LegalDocumentType $type,
        string $acceptanceText,
        ?string $context = null,
    ): self {
        return new self(
            $type,
            null,
            null,
            ConsentEvidenceValue::checksum($acceptanceText),
            ConsentEvidenceValue::normalizeContext($context),
        );
    }

    /** @throws \InvalidArgumentException */
    public static function versionedDocument(
        LegalDocumentVersion $version,
        string $acceptanceText,
        ?string $context = null,
    ): self {
        return new self(
            $version,
            null,
            $version->getChecksum(),
            ConsentEvidenceValue::checksum($acceptanceText),
            ConsentEvidenceValue::normalizeContext($context),
        );
    }

    /** @throws \InvalidArgumentException */
    public static function externalDocument(
        string $documentRef,
        string $documentChecksum,
        string $acceptanceText,
        ?string $context = null,
    ): self {
        $documentRef = trim($documentRef);
        if ($documentRef === '' || mb_strlen($documentRef) > 255) {
            throw new \InvalidArgumentException('An external document reference must contain at most 255 characters.');
        }

        return new self(
            null,
            $documentRef,
            $documentChecksum,
            ConsentEvidenceValue::checksum($acceptanceText),
            ConsentEvidenceValue::normalizeContext($context),
        );
    }

    /** @throws \InvalidArgumentException */
    public static function statement(string $acceptanceText, ?string $context = null): self
    {
        return new self(
            null,
            null,
            null,
            ConsentEvidenceValue::checksum($acceptanceText),
            ConsentEvidenceValue::normalizeContext($context),
        );
    }

    public function requestedDocumentType(): ?LegalDocumentType
    {
        return match (true) {
            $this->document instanceof LegalDocumentType => $this->document,
            $this->document instanceof LegalDocumentVersion => $this->document->getDocument()->getType(),
            default => null,
        };
    }

    public function documentVersion(): ?LegalDocumentVersion
    {
        return $this->document instanceof LegalDocumentVersion ? $this->document : null;
    }

    /** @throws \InvalidArgumentException */
    public function withDocumentVersion(LegalDocumentVersion $version): self
    {
        $requestedType = $this->requestedDocumentType();
        if ($requestedType !== null && $version->getDocument()->getType() !== $requestedType) {
            throw new \InvalidArgumentException('The resolved legal document version has an unexpected type.');
        }

        return new self($version, $this->documentRef, $version->getChecksum(), $this->textChecksum, $this->context);
    }

    public function documentRef(): ?string
    {
        return $this->documentRef;
    }

    public function documentChecksum(): ?string
    {
        return $this->documentChecksum;
    }

    public function textChecksum(): string
    {
        return $this->textChecksum;
    }

    public function context(): ?string
    {
        return $this->context;
    }
}
