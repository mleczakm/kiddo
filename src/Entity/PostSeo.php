<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class PostSeo
{
    #[ORM\Column(type: 'string', length: 70, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(type: 'string', length: 170, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $canonicalUrl = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $robotsIndex = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $robotsFollow = true;

    #[ORM\Column(type: 'string', length: 70, nullable: true)]
    private ?string $socialTitle = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $socialDescription = null;

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function shouldRobotsIndex(): bool
    {
        return $this->robotsIndex;
    }

    public function shouldRobotsFollow(): bool
    {
        return $this->robotsFollow;
    }

    public function getSocialTitle(): ?string
    {
        return $this->socialTitle;
    }

    public function getSocialDescription(): ?string
    {
        return $this->socialDescription;
    }

    public function updateSearch(
        ?string $seoTitle,
        ?string $seoDescription,
        ?string $canonicalUrl,
        bool $robotsIndex,
        bool $robotsFollow,
    ): void {
        $this->seoTitle = $seoTitle;
        $this->seoDescription = $seoDescription;
        $this->canonicalUrl = $canonicalUrl;
        $this->robotsIndex = $robotsIndex;
        $this->robotsFollow = $robotsFollow;
    }

    public function updateSocial(?string $socialTitle, ?string $socialDescription): void
    {
        $this->socialTitle = $socialTitle;
        $this->socialDescription = $socialDescription;
    }
}
