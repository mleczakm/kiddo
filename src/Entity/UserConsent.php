<?php

declare(strict_types=1);

namespace App\Entity;

use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\RequestContext;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: UserConsentRepository::class)]
#[ORM\Table(name: 'user_consent')]
#[ORM\Index(columns: ['user_id', 'type', 'revoked_at'], name: 'idx_user_consent_active')]
#[ORM\Index(columns: ['document_version_id'], name: 'idx_user_consent_document_version')]
final class UserConsent
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 40, enumType: ConsentType::class)]
    private ConsentType $type;

    #[ORM\ManyToOne(targetEntity: LegalDocumentVersion::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?LegalDocumentVersion $documentVersion;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $grantedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: 'string', length: 40, enumType: ConsentSource::class)]
    private ConsentSource $source;

    #[ORM\Embedded(class: ConsentAuditEvidence::class, columnPrefix: false)]
    private ConsentAuditEvidence $evidence;

    /** @throws \InvalidArgumentException */
    public function __construct(
        User $user,
        ConsentType $type,
        ConsentSource $source,
        ConsentEvidence $evidence,
        RequestContext $requestContext,
    ) {
        $documentType = $evidence->requestedDocumentType();
        $documentVersion = $evidence->documentVersion();
        if ($documentType !== null && $documentVersion === null) {
            throw new \InvalidArgumentException('Versioned legal consent evidence must be resolved before recording.');
        }
        $this->id = new Ulid();
        $this->user = $user;
        $this->type = $type;
        $this->source = $source;
        $this->documentVersion = $documentVersion;
        $this->evidence = new ConsentAuditEvidence($type, $evidence, $requestContext);
        $this->grantedAt = Clock::get()->now();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): ConsentType
    {
        return $this->type;
    }

    public function getDocumentType(): ?LegalDocumentType
    {
        return $this->evidence->documentType();
    }

    public function getDocumentVersion(): ?LegalDocumentVersion
    {
        return $this->documentVersion;
    }

    public function getDocumentRef(): ?string
    {
        return $this->evidence->documentRef();
    }

    public function getDocumentChecksum(): ?string
    {
        return $this->evidence->documentChecksum();
    }

    public function getGrantedAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getSource(): ConsentSource
    {
        return $this->source;
    }

    public function getIp(): ?string
    {
        return $this->evidence->ip();
    }

    public function getUserAgent(): ?string
    {
        return $this->evidence->userAgent();
    }

    public function getTextChecksum(): string
    {
        return $this->evidence->textChecksum();
    }

    public function getContext(): ?string
    {
        return $this->evidence->context();
    }

    public function revoke(?\DateTimeImmutable $at = null): void
    {
        $this->revokedAt ??= $at ?? Clock::get()->now();
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null;
    }
}
