<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use libphonenumber\PhoneNumber;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity('email', message: 'user.mail_uniqueness')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: 'json', options: [
        'jsonb' => true,
        'default' => '[]',
    ])]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: 'phone_number', nullable: true)]
    private ?PhoneNumber $phone = null;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column]
    private bool $newsletterSubscribed = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $newsletterConsentDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user')]
    private Collection $bookings;

    /**
     * @var Collection<int, Child>
     */
    #[ORM\OneToMany(targetEntity: Child::class, mappedBy: 'owner', cascade: ['remove'])]
    private Collection $children;

    public function __construct(?string $email = null, ?string $name = null)
    {
        $this->createdAt = Clock::get()->now();
        $this->bookings = new ArrayCollection();
        $this->children = new ArrayCollection();
        if ($email !== null) {
            $this->setEmail($email);
        }
        if ($name !== null) {
            $this->setName($name);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Child>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Checks the role as stored on this user directly — does not expand
     * role_hierarchy (e.g. an admin who inherits ROLE_HOST via the
     * hierarchy still returns false for hasRole('ROLE_HOST')). Use
     * is_granted()/Security::isGranted() when the hierarchy should apply.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @see UserInterface
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEmailString(): string
    {
        if ($this->name) {
            return sprintf('%s <%s>', $this->name, $this->email);
        }
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getPhone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function setPhone(?PhoneNumber $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function isNewsletterSubscribed(): bool
    {
        return $this->newsletterSubscribed;
    }

    public function setNewsletterSubscribed(bool $newsletterSubscribed): static
    {
        $this->newsletterSubscribed = $newsletterSubscribed;

        return $this;
    }

    public function getNewsletterConsentDate(): ?\DateTimeImmutable
    {
        return $this->newsletterConsentDate;
    }

    public function setNewsletterConsentDate(?\DateTimeImmutable $newsletterConsentDate): static
    {
        $this->newsletterConsentDate = $newsletterConsentDate;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }
}
