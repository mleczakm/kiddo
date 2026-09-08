<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Notifier;

use App\Application\Notification\EmailAttachment;
use App\Application\Notification\NotificationSenderInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

readonly class SymfonyNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private NotifierInterface $notifier,
    ) {}

    /**
     * @throws \LogicException when attachments are present but the Twig CSS-inliner/Inky extensions are missing
     */
    #[\Override]
    public function send(string $email, string $subject, string $content, array $attachments = []): void
    {
        $notification = $attachments === []
            ? new Notification()
                ->importance('')
                ->subject($subject)
                ->content($content)
            : $this->attachmentNotification($subject, $content, $attachments);

        $this->notifier->send($notification, new Recipient($email));
    }

    /**
     * The plain Notifier path has no hook for attachments, so build the
     * NotificationEmail ourselves via EmailNotificationInterface while still
     * letting the email channel apply the configured sender/envelope.
     *
     * @param list<EmailAttachment> $attachments
     *
     * @throws \LogicException when the Twig CSS-inliner/Inky extensions are missing
     */
    private function attachmentNotification(string $subject, string $content, array $attachments): Notification
    {
        return new class($subject, $content, $attachments) extends Notification implements EmailNotificationInterface {
            /**
             * @param list<EmailAttachment> $attachments
             */
            public function __construct(
                string $subject,
                private readonly string $htmlContent,
                private readonly array $attachments,
            ) {
                parent::__construct($subject);
                $this->importance('');
                $this->content($htmlContent);
            }

            #[\Override]
            public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): ?EmailMessage
            {
                $email = new NotificationEmail()
                    ->to($recipient->getEmail())
                    ->subject($this->getSubject())
                    ->content($this->htmlContent)
                    ->importance('');

                foreach ($this->attachments as $attachment) {
                    $email->attach($attachment->body, $attachment->filename, $attachment->contentType);
                }

                return new EmailMessage($email);
            }
        };
    }
}
