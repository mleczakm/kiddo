<?php

declare(strict_types=1);

namespace App\Application\Notification;

/**
 * A single file to attach to an outgoing notification email (e.g. an .ics
 * calendar file bundled with a booking confirmation).
 */
final readonly class EmailAttachment
{
    public function __construct(
        public string $filename,
        public string $body,
        public string $contentType = 'application/octet-stream',
    ) {}
}
