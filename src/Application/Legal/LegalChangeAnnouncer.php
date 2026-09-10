<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Application\Notification\NotificationSenderInterface;
use App\Application\Service\InAppNotificationService;
use App\Entity\LegalDocumentVersion;
use App\Entity\NotificationSeverity;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Sends the "we changed a legal document" e-mail and in-app notice to a batch of users. */
final readonly class LegalChangeAnnouncer
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private InAppNotificationService $inAppNotifications,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param array<User> $recipients
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function announce(LegalDocumentVersion $version, array $recipients): void
    {
        $documentType = $version->getDocument()->getType();
        $label = $documentType->label();
        $route = $documentType->routeName();

        $subject = $this->translator->trans('legal.change_notification.subject', ['document' => $label], 'emails');
        $body = $this->translator->trans(
            'legal.change_notification.body',
            [
                'document' => $label,
                'summary' => $version->getChangeSummary() ?? $this->translator->trans(
                    'legal.change_notification.no_summary',
                    [],
                    'emails',
                ),
                'effective' => $version->getEffectiveFrom()->format('Y-m-d'),
                'url' => $this->urlGenerator->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
            'emails',
        );

        foreach ($recipients as $recipient) {
            $this->notificationSender->send($recipient->getEmail(), $subject, $body);
        }

        $this->inAppNotifications->notifyUsers(
            $recipients,
            $this->translator->trans('notifications.in_app.legal_change.title', ['document' => $label]),
            $this->translator->trans('notifications.in_app.legal_change.body'),
            $this->urlGenerator->generate($route),
            NotificationSeverity::Info,
        );
    }
}
