<?php

declare(strict_types=1);

namespace App\Entity;

use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\RequestContext;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class ConsentAuditEvidence
{
    #[ORM\Column(name: 'document_type', type: 'string', length: 40, nullable: true, enumType: LegalDocumentType::class)]
    private ?LegalDocumentType $documentType;

    #[ORM\Column(name: 'document_ref', type: 'string', length: 255, nullable: true)]
    private ?string $documentRef;

    #[ORM\Column(name: 'document_checksum', type: 'string', length: 64, nullable: true)]
    private ?string $documentChecksum;

    #[ORM\Column(name: 'ip', type: 'string', length: 45, nullable: true)]
    private ?string $ip;

    #[ORM\Column(name: 'user_agent', type: 'string', length: 1000, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(name: 'text_checksum', type: 'string', length: 64)]
    private string $textChecksum;

    #[ORM\Column(name: 'context', type: 'string', length: 255, nullable: true)]
    private ?string $context;

    /** @throws \InvalidArgumentException */
    public function __construct(ConsentType $type, ConsentEvidence $evidence, RequestContext $requestContext)
    {
        $documentType = $evidence->requestedDocumentType();
        $expectedDocumentType = $type->documentType();
        $isExternalClassesTerms =
            $type === ConsentType::CLASSES_TERMS
            && $evidence->documentRef() !== null
            && $evidence->documentChecksum() !== null;
        if ($expectedDocumentType !== null && $documentType !== $expectedDocumentType && !$isExternalClassesTerms) {
            throw new \InvalidArgumentException('The consent type and legal document type do not match.');
        }

        $this->documentType = $documentType;
        $this->documentRef = $evidence->documentRef();
        $this->documentChecksum = $evidence->documentChecksum();
        $this->textChecksum = $evidence->textChecksum();
        $this->context = $evidence->context();
        $this->ip = $requestContext->ip();
        $this->userAgent = $requestContext->userAgent();
    }

    public function documentType(): ?LegalDocumentType
    {
        return $this->documentType;
    }

    public function documentRef(): ?string
    {
        return $this->documentRef;
    }

    public function documentChecksum(): ?string
    {
        return $this->documentChecksum;
    }

    public function ip(): ?string
    {
        return $this->ip;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
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
