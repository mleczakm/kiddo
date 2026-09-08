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

    /**
     * The admin-editable organization details row, source of truth for the
     * BLIK phone + bank account shown on every payment instruction.
     */
    public function asOrganizationDetails(
        string $blikPhone = '571 531 213',
        string $bankAccount = '46 2490 0005 0000 4000 1897 5420',
        string $facebookUrl = '',
    ): static {
        return $this->withKey('organization_details')->withContent([
            'name' => 'Warsztatownia Sensoryczna',
            'street' => 'Aleja Jana Pawła II 12D',
            'postal_code' => '05-250',
            'city' => 'Radzymin',
            'email' => 'warsztatownia.sensoryczna@gmail.com',
            'phone' => '+48 571 531 213',
            'bank_account' => $bankAccount,
            'blik_phone' => $blikPhone,
            'facebook_url' => $facebookUrl,
        ]);
    }

    #[\Override]
    public function assemble(): Setting
    {
        $setting = new Setting();
        $setting->setKey($this->properties['key'] ?? 'organization_details');
        $setting->setContent(
            $this->properties['content'] ?? [
                'name' => 'Warsztatownia Sensoryczna',
                'street' => 'Aleja Jana Pawła II 12D',
                'postal_code' => '05-250',
                'city' => 'Radzymin',
                'email' => 'warsztatownia.sensoryczna@gmail.com',
                'phone' => '+48 571 531 213',
                'bank_account' => '46 2490 0005 0000 4000 1897 5420',
                'blik_phone' => '571 531 213',
            ],
        );

        return $setting;
    }
}
