<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Brick\Money\Money;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;

class PlatformBillingService
{
    private const string PLATFORM_BILLING_KEY = 'platform_billing';

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * @return array{currentDue: string, pastDue: string}
     */
    public function getBillingData(): array
    {
        $setting = $this->settingRepository->findOneByKey(self::PLATFORM_BILLING_KEY);

        if ($setting === null) {
            return [
                'currentDue' => '0.00',
                'pastDue' => '0.00',
            ];
        }

        $content = $setting->getContent();
        if (! is_array($content)) {
            return [
                'currentDue' => '0.00',
                'pastDue' => '0.00',
            ];
        }

        return [
            'currentDue' => is_string($content['currentDue'] ?? null) ? $content['currentDue'] : '0.00',
            'pastDue' => is_string($content['pastDue'] ?? null) ? $content['pastDue'] : '0.00',
        ];
    }

    /**
     * Add 2% commission to current due when a payment is marked as paid
     */
    public function addCommissionToCurrentDue(Money $paymentAmount): void
    {
        $commission = $paymentAmount->multipliedBy(0.02, RoundingMode::HALF_UP);
        $currentDue = Money::of($this->getBillingData()['currentDue'], 'PLN');
        $newCurrentDue = $currentDue->plus($commission);

        $this->updateBillingData(
            $newCurrentDue->getAmount()
                ->toFloat(),
            (float) $this->getBillingData()['pastDue']
        );
    }

    /**
     * Process payment from super admin to lower past due
     * If payment amount > past due, lower current due (even to negative)
     */
    public function processPastDuePayment(float $paymentAmount): void
    {
        $billingData = $this->getBillingData();
        $pastDue = (float) $billingData['pastDue'];
        $currentDue = (float) $billingData['currentDue'];

        $newPastDue = max(0.0, $pastDue - $paymentAmount);

        if ($paymentAmount > $pastDue) {
            $excess = $paymentAmount - $pastDue;
            $newCurrentDue = $currentDue - $excess;
        } else {
            $newCurrentDue = $currentDue;
        }

        $this->updateBillingData($newCurrentDue, $newPastDue);
    }

    /**
     * Set past due as paid (move all past due to current due as negative)
     */
    public function setPastDueAsPaid(): void
    {
        $billingData = $this->getBillingData();
        $pastDue = (float) $billingData['pastDue'];
        $currentDue = (float) $billingData['currentDue'];

        $newPastDue = 0.0;
        $newCurrentDue = $currentDue - $pastDue;

        $this->updateBillingData($newCurrentDue, $newPastDue);
    }

    /**
     * Update billing data in settings
     */
    private function updateBillingData(float $currentDue, float $pastDue): void
    {
        $setting = $this->settingRepository->findOneByKey(self::PLATFORM_BILLING_KEY);

        if ($setting === null) {
            $setting = new Setting();
            $setting->setKey(self::PLATFORM_BILLING_KEY);
            $this->entityManager->persist($setting);
        }

        $setting->setContent([
            'currentDue' => number_format($currentDue, 2, '.', ''),
            'pastDue' => number_format($pastDue, 2, '.', ''),
        ]);

        $this->entityManager->flush();
    }

    public function getCurrentDue(): Money
    {
        return Money::of($this->getBillingData()['currentDue'], 'PLN');
    }

    public function getPastDue(): Money
    {
        return Money::of($this->getBillingData()['pastDue'], 'PLN');
    }

    public function hasPastDue(): bool
    {
        return $this->getPastDue()
            ->isGreaterThan(Money::zero('PLN'));
    }
}
