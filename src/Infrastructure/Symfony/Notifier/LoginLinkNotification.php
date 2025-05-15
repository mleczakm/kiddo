<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Notifier;

use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class LoginLinkNotification extends \Symfony\Component\Security\Http\LoginLink\LoginLinkNotification
{
    public function asEmailMessage(
        EmailRecipientInterface $recipient,
        ?string $transport = null
    ): ?\Symfony\Component\Notifier\Message\EmailMessage {
        $emailMessage = parent::asEmailMessage($recipient, $transport);

        // get the NotificationEmail object and override the template
        $email = $emailMessage->getMessage();
        $email->htmlTemplate('email/base.html.twig');

        return $emailMessage;
    }
}
