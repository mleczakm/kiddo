<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Uid\Ulid;

/**
 * Fan-out request: tell every account holder that a legal document has a new
 * version. Routed async and made idempotent by LegalDocumentVersion::notifiedAt,
 * so a retry (or a double publish) never double-mails anyone.
 */
final readonly class NotifyLegalDocumentChange
{
    public function __construct(
        public Ulid $versionId,
    ) {}
}
