<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SendReservationNotification;
use App\Application\Notification\NotificationSenderInterface;
use App\Application\Repository\SettingRepositoryInterface;
use App\Application\Repository\UserRepositoryInterface;
use App\Application\Service\InAppNotificationService;
use App\Application\Templating\TemplateRendererInterface;
use App\Entity\NotificationSeverity;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SendReservationNotificationHandler
{
    private const string DEFAULT_BLIK_PHONE = '571 531 213';

    private const string DEFAULT_BANK_ACCOUNT = '46 2490 0005 0000 4000 1897 5420';

    public function __construct(
        private NotificationSenderInterface $notificationSender,
        private TranslatorInterface $translator,
        private TemplateRendererInterface $templateRenderer,
        private InAppNotificationService $inAppNotifications,
        private UserRepositoryInterface $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private SettingRepositoryInterface $settingRepository,
    ) {}

    public function __invoke(SendReservationNotification $command): void
    {
        $translatorContext = [
            'paymentCode' => $command->paymentCode,
            'paymentAmount' => $command->paymentAmount,
            'blikPhoneNumber' => $this->paymentSetting('blik_phone', self::DEFAULT_BLIK_PHONE),
            'bankAccountNumber' => $this->paymentSetting('bank_account', self::DEFAULT_BANK_ACCOUNT),
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

    private function paymentSetting(string $key, string $default): string
    {
        $setting = $this->settingRepository->findOneByKey('payment');
        $content = $setting?->getContent();
        if (!is_array($content)) {
            return $default;
        }

        $value = $content[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
