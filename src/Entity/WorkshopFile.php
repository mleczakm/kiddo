<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'workshop_file')]
#[ORM\UniqueConstraint(name: 'uniq_workshop_file', fields: ['metadata', 'file'])]
#[ORM\Index(columns: ['lesson_metadata_id', 'position'], name: 'idx_workshop_file_position')]
class WorkshopFile
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: LessonMetadata::class, inversedBy: 'files')]
        #[ORM\JoinColumn(name: 'lesson_metadata_id', nullable: false, onDelete: 'CASCADE')]
        private LessonMetadata $metadata,
        #[ORM\ManyToOne(targetEntity: File::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private File $file,
        #[ORM\Column(type: 'string', length: 20, enumType: WorkshopFileRole::class)]
        private WorkshopFileRole $role = WorkshopFileRole::ATTACHMENT,
        #[ORM\Column(type: 'integer')]
        private int $position = 0,
        #[ORM\Column(type: 'string', length: 1000, nullable: true)]
        private ?string $caption = null,
    ) {
        if ($this->position < 0) {
            throw new \InvalidArgumentException('Attachment position cannot be negative.');
        }

        $this->id = new Ulid();
        $this->caption = self::nullableTrim($this->caption);
        $this->metadata->addFile($this);
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getMetadata(): LessonMetadata
    {
        return $this->metadata;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function getRole(): WorkshopFileRole
    {
        return $this->role;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /** @throws \DomainException */
    public function setRole(WorkshopFileRole $role): void
    {
        if (
            $role === WorkshopFileRole::TERMS_OF_USE
            && $this->metadata->getTermsAttachment() !== null
            && $this->metadata->getTermsAttachment() !== $this
        ) {
            throw new \DomainException('A workshop can have only one terms-of-use attachment.');
        }

        $this->role = $role;
    }

    /** @throws \InvalidArgumentException */
    public function setPosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Attachment position cannot be negative.');
        }

        $this->position = $position;
    }

    public function setCaption(?string $caption): void
    {
        $this->caption = self::nullableTrim($caption);
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
