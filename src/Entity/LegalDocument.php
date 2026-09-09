<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\LegalDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: LegalDocumentRepository::class)]
#[ORM\Table(name: 'legal_document')]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_type', fields: ['type'])]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_slug', fields: ['slug'])]
final class LegalDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 40, enumType: LegalDocumentType::class)]
    private LegalDocumentType $type;

    #[ORM\Column(type: 'string', length: 80)]
    private string $slug;

    /** @var Collection<int, LegalDocumentVersion> */
    #[ORM\OneToMany(
        targetEntity: LegalDocumentVersion::class,
        mappedBy: 'document',
        cascade: ['persist'],
        orphanRemoval: false,
    )]
    #[ORM\OrderBy(['version' => 'DESC'])]
    private Collection $versions;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(LegalDocumentType $type)
    {
        $this->id = new Ulid();
        $this->type = $type;
        $this->slug = $type->slug();
        $this->versions = new ArrayCollection();
        $this->createdAt = Clock::get()->now();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getType(): LegalDocumentType
    {
        return $this->type;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    /** @return Collection<int, LegalDocumentVersion> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function nextVersionNumber(): int
    {
        $maximum = 0;
        foreach ($this->versions as $version) {
            $maximum = max($maximum, $version->getVersion());
        }

        return $maximum + 1;
    }

    /** @throws \InvalidArgumentException */
    public function addVersion(LegalDocumentVersion $version): void
    {
        if ($version->getDocument() !== $this) {
            throw new \InvalidArgumentException('The version belongs to a different legal document.');
        }

        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
        }
    }
}
