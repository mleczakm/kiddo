<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_tenant_domain', columns: ['domain'])])]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

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

    public function __construct(
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
        private ?string $domain,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $emailFrom = null,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $blikPhone = null,
        #[ORM\Column(type: 'string', length: 34, nullable: true)]
        private ?string $transferAccount = null,
    ) {
        $this->id = new Ulid();
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

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getEmailFrom(): ?string
    {
        return $this->emailFrom;
    }

    public function setEmailFrom(?string $emailFrom): void
    {
        $this->emailFrom = $emailFrom;
    }

    public function getBlikPhone(): ?string
    {
        return $this->blikPhone;
    }

    public function setBlikPhone(?string $blikPhone): void
    {
        $this->blikPhone = $blikPhone;
    }

    public function getTransferAccount(): ?string
    {
        return $this->transferAccount;
    }

    public function setTransferAccount(?string $transferAccount): void
    {
        $this->transferAccount = $transferAccount;
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
