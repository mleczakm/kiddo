<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: LegalDocumentVersionRepository::class)]
#[ORM\Table(name: 'legal_document_version')]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_version', fields: ['document', 'version'])]
#[ORM\Index(columns: ['document_id', 'published_at', 'effective_from'], name: 'idx_legal_document_publication')]
final class LegalDocumentVersion
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LegalDocument $document;

    #[ORM\Column(type: 'integer')]
    private int $version;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private File $file;

    #[ORM\Column(type: 'string', length: 64)]
    private string $checksum;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $effectiveFrom;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $publishedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $publishedBy;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    private ?string $changeSummary;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @throws \InvalidArgumentException */
    public function __construct(
        LegalDocument $document,
        File $file,
        \DateTimeImmutable $effectiveFrom,
        User $publishedBy,
        ?string $changeSummary = null,
    ) {
        $changeSummary = $changeSummary === null ? null : trim($changeSummary);
        if ($changeSummary === '') {
            $changeSummary = null;
        }
        if ($changeSummary !== null && mb_strlen($changeSummary) > 1000) {
            throw new \InvalidArgumentException('A legal document change summary cannot exceed 1000 characters.');
        }

        $this->id = new Ulid();
        $this->document = $document;
        $this->version = $document->nextVersionNumber();
        $this->file = $file;
        $this->checksum = $file->getChecksum();
        $this->effectiveFrom = $effectiveFrom;
        $this->publishedBy = $publishedBy;
        $this->changeSummary = $changeSummary;
        $this->publishedAt = Clock::get()->now();
        $this->createdAt = $this->publishedAt;
        $this->document->addVersion($this);
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getPublishedBy(): ?User
    {
        return $this->publishedBy;
    }

    public function getChangeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isEffectiveAt(\DateTimeImmutable $now): bool
    {
        return $this->publishedAt <= $now && $this->effectiveFrom <= $now;
    }
}
