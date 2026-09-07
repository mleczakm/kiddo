<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendReservationNotification;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Application\Service\OrganizationDetailsProvider;
use App\Application\Templating\TemplateRendererInterface;
use App\Entity\NotificationSeverity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SendReservationNotificationHandler
{
    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private TranslatorInterface $translator,
        private TemplateRendererInterface $templateRenderer,
        private InAppNotificationService $inAppNotifications,
        private UserRepositoryInterface $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private OrganizationDetailsProvider $organizationDetails,
    ) {}

    public function __invoke(SendReservationNotification $command): void
    {
        $organization = $this->organizationDetails->get();
        $translatorContext = [
            'paymentCode' => $command->paymentCode,
            'paymentAmount' => $command->paymentAmount,
            'blikPhoneNumber' => $organization->blikPhone,
            'bankAccountNumber' => $organization->bankAccount,
            'lessonTitle' => $command->lessonTitle,
            'lessonSchedule' => $command->lessonSchedule,
            'ticketType' => $command->ticketType,
            'childName' => $command->childName,
        ];

        $subject = $this->translator->trans('reservation.subject', [], 'emails');
        $content = $this->templateRenderer->render('email/reservation.html.twig', $translatorContext);

        $this->notificationSender->send($command->email, $subject, $content);

        $user = $this->userRepository->findOneBy([
            'email' => $command->email,
        ]);
        if ($user !== null) {
            $this->inAppNotifications->notify(
                $user,
                $this->translator->trans('notifications.in_app.reservation.title', [], 'messages'),
                $this->translator->trans(
                    'notifications.in_app.reservation.body',
                    [
                        'code' => $command->paymentCode,
                        'amount' => (string) $command->paymentAmount->getAmount(),
                    ],
                    'messages',
                ),
                $this->urlGenerator->generate('dashboard'),
                NotificationSeverity::Success,
            );
        }
    }
}
