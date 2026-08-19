<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuHookLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: MenuHookLinkRepository::class)]
#[ORM\Table(name: 'menu_hook_link')]
#[ORM\Index(columns: ['slot_key', 'position'], name: 'idx_menu_hook_slot_position')]
class MenuHookLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    /** @throws \InvalidArgumentException */
    public function __construct(
        #[ORM\Column(type: 'string', length: 100)]
        private string $slotKey,
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\Column(type: 'string', length: 20, enumType: MenuHookLinkTarget::class)]
        private MenuHookLinkTarget $targetType,
        #[ORM\Column(type: 'string', length: 2048)]
        private string $target,
        #[ORM\Column(type: 'string', length: 255)]
        private string $label,
    ) {
        if ($this->position < 0) {
            throw new \InvalidArgumentException('Menu link position cannot be negative.');
        }
        $this->id = new Ulid();
    }

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getSlotKey(): string
    {
        return $this->slotKey;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getTargetType(): MenuHookLinkTarget
    {
        return $this->targetType;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
