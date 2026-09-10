<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The reversible/irreversible closure state of a {@see User} (Stage 11).
 * deactivatedAt = suspended, no login. deletionRequestedAt = grace period
 * running; a login clears both. anonymizedAt = PII scrubbed, permanently
 * locked. Mapped column-for-column onto the user table (columnPrefix: false).
 */
#[ORM\Embeddable]
final class AccountLifecycle
{
    #[ORM\Column(name: 'deactivated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deactivatedAt = null;

    #[ORM\Column(name: 'deletion_requested_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletionRequestedAt = null;

    #[ORM\Column(name: 'anonymized_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    public function deactivatedAt(): ?\DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function deletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    public function anonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    public function isClosed(): bool
    {
        return $this->deactivatedAt !== null || $this->deletionRequestedAt !== null;
    }

    public function deactivate(\DateTimeImmutable $at): void
    {
        $this->deactivatedAt ??= $at;
    }

    public function requestDeletion(\DateTimeImmutable $at): void
    {
        if ($this->anonymizedAt !== null) {
            return;
        }
        $this->deletionRequestedAt ??= $at;
        $this->deactivatedAt ??= $at;
    }

    public function reactivate(): void
    {
        $this->deactivatedAt = null;
        $this->deletionRequestedAt = null;
    }

    public function markAnonymized(\DateTimeImmutable $at): void
    {
        $this->anonymizedAt ??= $at;
        $this->deactivatedAt ??= $at;
    }
}
