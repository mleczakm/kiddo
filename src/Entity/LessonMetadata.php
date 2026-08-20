<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\LessonMetadataRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Ulid;

/**
 * A workshop's content: title, description, visual theme, image, etc. A
 * Lesson occurrence references one of these — occurrences generated
 * together (e.g. by ExtendSeriesScheduleHandler) share the same row instead
 * of each duplicating a full copy, since this content is normally identical
 * across a series' occurrences. Only `schedule` (each occurrence's own
 * date/time) lives on Lesson directly, since that's the one thing that must
 * always differ per occurrence.
 */
#[ORM\Entity(repositoryClass: LessonMetadataRepository::class)]
class LessonMetadata
{
    public const string DEFAULT_VISUAL_THEME = '#f97316';

    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /** @var Collection<int, WorkshopFile> */
    #[ORM\OneToMany(
        targetEntity: WorkshopFile::class,
        mappedBy: 'metadata',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC'])]
    public Collection $files;

    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        public string $title,
        #[ORM\Column(type: 'text')]
        public string $lead,
        #[ORM\Column(type: 'string', length: 255)]
        public string $visualTheme,
        #[ORM\Column(type: 'text')]
        public string $description,
        #[ORM\Column(type: 'integer')]
        public int $capacity,
        #[ORM\Column(type: 'integer')]
        public int $duration,
        #[ORM\Embedded(class: AgeRange::class)]
        public AgeRange $ageRange,
        #[ORM\Column(type: 'string', length: 50)]
        public string $category,
        // Not unique: series occurrences share the same title/slug.
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        public ?string $slug = null,
        // Base64-encoded image bytes, stored alongside the mime type. Kept
        // as a plain string (not Doctrine's 'blob' type, which hydrates to a
        // stream resource) so it composes cleanly with this class's
        // clone-based with*() immutability pattern.
        #[ORM\Column(type: 'text', nullable: true)]
        public ?string $imageData = null,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        public ?string $imageMimeType = null,
    ) {
        $this->id = new Ulid();
        $this->files = new ArrayCollection();
        if ($this->slug === null || $this->slug === '') {
            $this->slug = self::slugify($this->title);
        }
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public static function slugify(string $title): string
    {
        return new AsciiSlugger()
            ->slug($title)
            ->lower()
            ->toString();
    }

    /**
     * visualTheme is meant to be a CSS hex color (edited via a native color
     * picker). Older rows may still hold a free-text value from before that
     * (e.g. a Tailwind-style token like "orange-100"), which isn't valid CSS
     * and would silently fail as a background-color — fall back to a
     * default color instead of rendering nothing.
     */
    public function getSafeVisualTheme(): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $this->visualTheme) === 1
            ? $this->visualTheme
            : self::DEFAULT_VISUAL_THEME;
    }

    public function withTitle(string $title): self
    {
        $new = $this->duplicate();
        $new->title = $title;
        $new->slug = self::slugify($title);

        return $new;
    }

    public function withLead(string $lead): self
    {
        $new = $this->duplicate();
        $new->lead = $lead;
        return $new;
    }

    public function withVisualTheme(string $visualTheme): self
    {
        $new = $this->duplicate();
        $new->visualTheme = $visualTheme;
        return $new;
    }

    public function withDescription(string $description): self
    {
        $new = $this->duplicate();
        $new->description = $description;
        return $new;
    }

    public function withCapacity(int $capacity): self
    {
        $new = $this->duplicate();
        $new->capacity = $capacity;
        return $new;
    }

    public function withDuration(int $duration): self
    {
        $new = $this->duplicate();
        $new->duration = $duration;
        return $new;
    }

    public function withAgeRange(AgeRange $ageRange): self
    {
        $new = $this->duplicate();
        $new->ageRange = $ageRange;
        return $new;
    }

    public function withCategory(string $category): self
    {
        $new = $this->duplicate();
        $new->category = $category;
        return $new;
    }

    public function withImage(?string $imageData, ?string $imageMimeType): self
    {
        $new = $this->duplicate();
        $new->imageData = $imageData;
        $new->imageMimeType = $imageMimeType;
        return $new;
    }

    public function hasImage(): bool
    {
        return $this->imageData !== null && $this->imageMimeType !== null;
    }

    public function isVideo(): bool
    {
        return $this->imageMimeType !== null && str_starts_with($this->imageMimeType, 'video/');
    }

    /** @throws \DomainException */
    public function addFile(WorkshopFile $file): void
    {
        if ($file->getMetadata() !== $this) {
            throw new \InvalidArgumentException('The attachment belongs to different workshop metadata.');
        }
        if ($this->files->contains($file)) {
            return;
        }
        if ($file->getRole() === WorkshopFileRole::TERMS_OF_USE && $this->getTermsAttachment() !== null) {
            throw new \DomainException('A workshop can have only one terms-of-use attachment.');
        }

        $this->files->add($file);
    }

    public function removeFile(WorkshopFile $file): void
    {
        $this->files->removeElement($file);
    }

    public function getTermsAttachment(): ?WorkshopFile
    {
        foreach ($this->files as $file) {
            if ($file->getRole() === WorkshopFileRole::TERMS_OF_USE) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The metadata value object receives a fresh database identity on every
     * with*() edit. Copy the attachment join rows as part of that operation
     * while continuing to reuse the underlying stored File records.
     */
    private function duplicate(): self
    {
        $new = clone $this;
        $new->id = new Ulid();
        $new->files = new ArrayCollection();

        foreach ($this->files as $workshopFile) {
            new WorkshopFile(
                $new,
                $workshopFile->getFile(),
                $workshopFile->getRole(),
                $workshopFile->getPosition(),
                $workshopFile->getCaption(),
            );
        }

        return $new;
    }
}
