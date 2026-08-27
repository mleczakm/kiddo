<?php

declare(strict_types=1);

namespace App\Application\Chat;

use App\Application\Repository\SettingRepositoryInterface;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\TicketOption;
use App\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class LessonPresenter
{
    /**
     * Matches CheckExpiredPaymentsHandler::EXPIRATION_WORKING_HOURS - the code
     * is valid for 24 working hours, so this wall-clock figure is a lower bound
     * (the copy that uses it already hedges with "~").
     */
    public const int PAYMENT_CODE_VALID_HOURS = 24;

    public function __construct(
        private SettingRepositoryInterface $settingRepository,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     schedule: string,
     *     duration_minutes: int,
     *     capacity: int,
     *     available_spots: int,
     *     status: string,
     *     age_min: int,
     *     age_max: int,
     *     category: string,
     *     slug: string|null,
     *     lead: string,
     *     ticket_options: array<int, array{
     *         type: string,
     *         price: string,
     *         currency: string,
     *         description: string,
     *         reschedule_policy: string
     *     }>,
     *     series_id: string|null
     * }
     */
    public function lesson(Lesson $lesson): array
    {
        $meta = $lesson->getMetadata();

        return [
            'id' => (string) $lesson->getId(),
            'title' => $meta->title,
            'schedule' => $lesson->schedule->format(\DateTimeInterface::ATOM),
            'duration_minutes' => $meta->duration,
            'capacity' => $meta->capacity,
            'available_spots' => $lesson->getAvailableSpots(),
            'status' => $lesson->status,
            'age_min' => $meta->ageRange->min,
            'age_max' => $meta->ageRange->max ?? $meta->ageRange->min,
            'category' => $meta->category,
            'slug' => $meta->slug,
            'lead' => $meta->lead,
            'ticket_options' => array_map($this->ticketOption(...), $lesson->getTicketOptions()),
            'series_id' => $lesson->getSeries() ? (string) $lesson->getSeries()->getId() : null,
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     price: string,
     *     currency: string,
     *     description: string,
     *     reschedule_policy: string
     * }
     */
    public function ticketOption(TicketOption $option): array
    {
        return [
            'type' => $option->type->value,
            'price' => (string) $option->price->getAmount()->toFloat(),
            'currency' => $option->price->getCurrency()->getCurrencyCode(),
            'description' => $option->description,
            'reschedule_policy' => $option->reschedulePolicy->value,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     created_at: string,
     *     child_id: string|null,
     *     child_name: string|null,
     *     notes: string|null,
     *     payment: array<string, mixed>|null,
     *     lessons: array<int, array{
     *         id: string,
     *         title: string,
     *         schedule: string,
     *         cancelled: bool,
     *         rescheduled: bool
     *     }>
     * }
     */
    public function booking(Booking $booking): array
    {
        $payment = $booking->getPayment();
        $lessons = [];
        foreach ($booking->getLessons() as $lesson) {
            $lessons[] = [
                'id' => (string) $lesson->getId(),
                'title' => $lesson->getMetadata()->title,
                'schedule' => $lesson->schedule->format(\DateTimeInterface::ATOM),
                'cancelled' => $booking->isLessonCancelled($lesson),
                'rescheduled' => $booking->isLessonRescheduled($lesson),
            ];
        }

        return [
            'id' => (string) $booking->getId(),
            'status' => $booking->getStatus(),
            'created_at' => $booking->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'child_id' => $booking->getChild() ? (string) $booking->getChild()->getId() : null,
            'child_name' => $booking->getChild()?->getName(),
            'notes' => $booking->getNotes(),
            'payment' => $payment !== null ? $this->paymentInstructions($payment) : null,
            'lessons' => $lessons,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     status: string,
     *     amount: string,
     *     currency: string,
     *     code: string|null,
     *     created_at: string,
     *     paid_at: string|null
     * }
     */
    public function payment(Payment $payment): array
    {
        return [
            'id' => (string) $payment->getId(),
            'status' => $payment->getStatus(),
            'amount' => (string) $payment->getAmount()->getAmount()->toFloat(),
            'currency' => $payment->getAmount()->getCurrency()->getCurrencyCode(),
            'code' => $payment->getPaymentCode()?->getCode(),
            'created_at' => $payment->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'paid_at' => $payment->getPaidAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Full BLIK / transfer copy for chat agents and APIs.
     *
     * @return array{
     *     id: string,
     *     status: string,
     *     amount: string,
     *     currency: string,
     *     code: string|null,
     *     created_at: string,
     *     paid_at: string|null,
     *     method: string,
     *     blik_phone: string,
     *     bank_account: string,
     *     expires_at: string,
     *     valid_hours: int,
     *     instruction_pl: string
     * }
     */
    public function paymentInstructions(Payment $payment): array
    {
        $base = $this->payment($payment);
        $blikPhone = $this->paymentSetting('blik_phone');
        $bankAccount = $this->paymentSetting('bank_account');
        $expiresAt = $payment->getCreatedAt()->modify(sprintf('+%d hours', self::PAYMENT_CODE_VALID_HOURS));
        $code = $base['code'] ?? '—';
        $amount = $base['amount'];
        $currency = $base['currency'];

        $instruction = $this->translator->trans(
            'payment.blik_instruction',
            [
                'blik_phone' => $blikPhone,
                'amount' => $amount,
                'currency' => $currency,
                'code' => $code,
                'valid_hours' => self::PAYMENT_CODE_VALID_HOURS,
                'expires_at' => $expiresAt->format('d.m.Y H:i'),
                'bank_account' => $bankAccount,
            ],
            'messages',
        );

        return [
            ...$base,
            'method' => 'blik_phone_transfer',
            'blik_phone' => $blikPhone,
            'bank_account' => $bankAccount,
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            'valid_hours' => self::PAYMENT_CODE_VALID_HOURS,
            'instruction_pl' => $instruction,
        ];
    }

    /**
     * @return array{
     *     id: int|null,
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     roles: array<string>,
     *     children_count: int
     * }
     */
    public function userSummary(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() !== null ? (string) $user->getPhone() : null,
            'roles' => $user->getRoles(),
            'children_count' => $user->getChildren()->count(),
        ];
    }

    private function paymentSetting(string $key): string
    {
        $setting = $this->settingRepository->findOneByKey('payment');
        $content = $setting?->getContent();
        if (!is_array($content)) {
            throw new \RuntimeException('Payment settings not configured');
        }

        $value = $content[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Payment setting %s not configured', $key));
        }

        return $value;
    }
}
