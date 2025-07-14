<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'tenants')]
    private Collection $users;

    /**
     * @var Collection<int, Setting>
     */
    #[ORM\OneToMany(mappedBy: 'tenant', targetEntity: Setting::class, cascade: ['persist', 'remove'])]
    private Collection $settings;

    public function __construct(string $name)
    {
        $this->id = new Ulid();
        $this->name = $name;
        $this->users = new ArrayCollection();
        $this->settings = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): void
    {
        if (! $this->users->contains($user)) {
            $this->users->add($user);
            $user->addTenant($this);
        }
    }

    public function removeUser(User $user): void
    {
        if ($this->users->contains($user)) {
            $this->users->removeElement($user);
            $user->removeTenant($this);
        }
    }

    /**
     * @return Collection<int, Setting>
     */
    public function getSettings(): Collection
    {
        return $this->settings;
    }

    public function addSetting(Setting $setting): void
    {
        if (! $this->settings->contains($setting)) {
            $this->settings->add($setting);
            $setting->setTenant($this);
        }
    }

    public function removeSetting(Setting $setting): void
    {
        if ($this->settings->contains($setting)) {
            $this->settings->removeElement($setting);
        }
    }
}
