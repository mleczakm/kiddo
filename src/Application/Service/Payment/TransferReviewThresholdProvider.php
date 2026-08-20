<?php

declare(strict_types=1);

namespace App\Application\Service\Payment;

use App\Application\Repository\SettingRepositoryInterface;
use Brick\Money\Money;

/**
 * Stage 6 hardening: the amount above which an incoming transfer is routed
 * to administrative review instead of being auto-matched. Admin-editable via
 * the Setting entity (see AdminSettingsComponent), matching the robots.txt
 * pattern - defaults to 1000 PLN when nothing has been configured yet.
 */
final readonly class TransferReviewThresholdProvider
{
    public const string SETTING_KEY = 'transfer_review_threshold_pln';

    private const int DEFAULT_THRESHOLD_PLN = 1000;

    public function __construct(
        private SettingRepositoryInterface $settingRepository,
    ) {}

    public function get(): Money
    {
        $setting = $this->settingRepository->findOneByKey(self::SETTING_KEY);
        $content = $setting?->getContent();
        $amount = is_array($content) && is_numeric($content['amount_pln'] ?? null)
            ? (int) $content['amount_pln']
            : self::DEFAULT_THRESHOLD_PLN;

        return Money::of($amount, 'PLN');
    }
}
