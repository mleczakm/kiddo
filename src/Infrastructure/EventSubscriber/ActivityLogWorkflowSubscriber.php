<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Service\ActivityLogger;
use App\Entity\ActivityType;
use App\Entity\Payment;
use App\Infrastructure\Twig\MoneyExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Event\Event;

/**
 * Logs activity for domain transitions that go through the Symfony Workflow
 * component rather than the message bus. `workflow.payment.transition.pay`
 * fires exactly once, only on a real pay transition (both for auto-matched
 * bank transfers and admin-approved on-place payments), which makes it a
 * more reliable signal than logging every MatchPaymentForTransfer dispatch
 * (that command also runs — and no-ops — for transfers that don't match).
 */
final readonly class ActivityLogWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.payment.transition.pay' => 'onPaymentPaid',
        ];
    }

    public function onPaymentPaid(Event $event): void
    {
        $payment = $event->getSubject();

        if (! $payment instanceof Payment) {
            return;
        }

        $user = $payment->getUser();
        $userId = $user->getId();

        $amount = new MoneyExtension()->formatMoney($payment->getAmount());

        $this->activityLogger->log(
            type: ActivityType::PAYMENT_RECEIVED,
            title: sprintf('%s opłacił/a rezerwację', $user->getName()),
            subject: $user,
            summary: sprintf('%s — %s', $amount, $payment->getBookingsSummary()),
            url: $userId !== null ? $this->urlGenerator->generate('app_admin_user_view', ['id' => $userId]) : null,
            context: ['paymentId' => (string) $payment->getId()],
            dedupeKey: 'payment_received:' . $payment->getId(),
        );
    }
}
