<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Setting
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $key;

    /**
     * @var ?array<string, mixed>
     */
    #[ORM\Column(type: 'json_document', options: [
        'jsonb' => true,
        'default' => '{}',
    ])]
    private mixed $content = [];

    #[ORM\ManyToOne(targetEntity: Tenant::class, inversedBy: 'settings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Tenant $tenant = null;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function getContent(): mixed
    {
        return $this->content;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function setContent(array $content): void
    {
        $this->content = $content;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }
}
