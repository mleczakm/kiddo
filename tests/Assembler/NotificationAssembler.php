<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Notification;
use App\Entity\NotificationSeverity;
use App\Entity\Tenant;
use App\Entity\User;

final class NotificationAssembler
{
    private ?Tenant $tenant = null;

    private ?User $user = null;

    private string $title = 'Notification';

    private ?string $body = null;

    private ?string $url = null;

    private NotificationSeverity $severity = NotificationSeverity::Info;

    private ?\DateTimeImmutable $createdAt = null;

    private function __construct() {}

    public static function new(): self
    {
        return new self();
    }

    public function withTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        return $this;
    }

    public function withUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function withBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function withUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function withSeverity(NotificationSeverity $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function assemble(): Notification
    {
        // Provide defaults if not set
        $tenant = $this->tenant ?? TenantAssembler::new()->assemble();
        $user = $this->user ?? UserAssembler::new()->assemble();

        $n = new Notification($tenant, $user, $this->title, $this->body, $this->url, $this->severity);

        if ($this->createdAt !== null) {
            $ref = new \ReflectionClass($n);
            $prop = $ref->getProperty('createdAt');
            $prop->setValue($n, $this->createdAt);
        }

        return $n;
    }
}
