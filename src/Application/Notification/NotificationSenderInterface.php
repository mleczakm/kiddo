<?php

declare(strict_types=1);

namespace App\Application\Notification;

interface NotificationSenderInterface
{
    /**
     * @param list<EmailAttachment> $attachments
     */
    public function send(string $email, string $subject, string $content, array $attachments = []): void;
}
