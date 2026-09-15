<?php

declare(strict_types=1);

namespace App\Entity;

use App\Infrastructure\Doctrine\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint', columns: ['endpoint'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_push_subscription_user')]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', length: 16)]
    private Ulid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        #[ORM\Column(type: 'string', length: 512)]
        private string $endpoint,
        #[ORM\Column(type: 'string', length: 255)]
        private string $p256dh,
        #[ORM\Column(type: 'string', length: 255)]
        private string $auth,
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable('now');
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getP256dh(): string
    {
        return $this->p256dh;
    }

    public function getAuth(): string
    {
        return $this->auth;
    }

    public function updateKeys(string $p256dh, string $auth): void
    {
        $this->p256dh = $p256dh;
        $this->auth = $auth;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
