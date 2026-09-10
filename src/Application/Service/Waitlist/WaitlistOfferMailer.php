<?php

declare(strict_types=1);

namespace App\Application\Service\Waitlist;

use App\Application\Notification\NotificationSenderInterface;
use App\Application\Service\InAppNotificationService;
use App\Application\Templating\TemplateRendererInterface;
use App\Entity\NotificationSeverity;
use App\Entity\WaitlistEntry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Sends the "a seat freed up — you have {@see WaitlistEntry::OFFER_TTL_MINUTES}
 * minutes to book it" notification (email + in-app) for a promoted waitlist entry.
 */
final readonly class WaitlistOfferMailer
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private TemplateRendererInterface $templateRenderer,
        private InAppNotificationService $inAppNotifications,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function notifyOffer(WaitlistEntry $entry): void
    {
        $lesson = $entry->getLesson();
        $meta = $lesson->getMetadata();

        $bookingUrl =
            $meta->slug !== null && $meta->slug !== ''
                ? $this->urlGenerator->generate(
                    'workshop_by_slug',
                    ['slug' => $meta->slug],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                )
                : $this->urlGenerator->generate('workshops', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $context = [
            'name' => $entry->getNameSnapshot() ?? $entry->getUser()->getName(),
            'lessonTitle' => $meta->title,
            'lessonDate' => $lesson->schedule->format('d.m.Y H:i'),
            'offerExpiresAt' => $entry->getOfferExpiresAt()?->format('d.m.Y H:i') ?? '',
            'holdMinutes' => WaitlistEntry::OFFER_TTL_MINUTES,
            'url' => $bookingUrl,
        ];

        $this->notificationSender->send(
            $entry->getEmailSnapshot(),
            $this->translator->trans('waitlist_offer.subject', ['lessonTitle' => $meta->title], 'emails'),
            $this->templateRenderer->render('email/waitlist-offer.html.twig', $context),
        );

        $this->inAppNotifications->notify(
            $entry->getUser(),
            $this->translator->trans('notifications.in_app.waitlist_offer.title', [], 'messages'),
            $this->translator->trans('notifications.in_app.waitlist_offer.body', $context, 'messages'),
            $this->urlGenerator->generate('dashboard'),
            NotificationSeverity::Success,
        );
    }
}
