<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Setting;

/**
 * @extends EntityAssembler<Setting>
 */
final class SettingAssembler extends EntityAssembler
{
    public function withKey(string $key): static
    {
        return $this->with('key', $key);
    }

    /**
     * @param array<string, mixed> $content
     */
    public function withContent(array $content): static
    {
        return $this->with('content', $content);
    }

    public function asPayment(
        string $blikPhone = '571 531 213',
        string $bankAccount = '46 2490 0005 0000 4000 1897 5420',
    ): static {
        return $this
            ->withKey('payment')
            ->withContent([
                'blik_phone' => $blikPhone,
                'bank_account' => $bankAccount,
            ]);
    }

    public function assemble(): Setting
    {
        $setting = new Setting();
        /** @phpstan-ignore-next-line */
        $setting->setKey($this->properties['key'] ?? 'payment');
        /** @phpstan-ignore-next-line */
        $setting->setContent($this->properties['content'] ?? [
            'blik_phone' => '571 531 213',
            'bank_account' => '46 2490 0005 0000 4000 1897 5420',
        ]);

        return $setting;
    }
}
