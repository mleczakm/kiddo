<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PostBody
{
    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $contentJson = ['type' => 'doc', 'content' => []];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contentHtml = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $linkedWorkshopSlug = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $eyebrow = null;

    #[ORM\Column(type: 'string', length: 320, nullable: true)]
    private ?string $excerpt = null;

    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        private string $title,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return array<string, mixed> */
    public function getContentJson(): array
    {
        return $this->contentJson;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function getLinkedWorkshopSlug(): ?string
    {
        return $this->linkedWorkshopSlug;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }
}
