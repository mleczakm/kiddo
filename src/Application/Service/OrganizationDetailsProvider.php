<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Repository\SettingRepositoryInterface;

/**
 * Reads the admin-editable "organization details" Setting (contact + payment
 * info), falling back to the historical hard-coded values when nothing has
 * been configured yet - same pattern as TransferReviewThresholdProvider.
 */
final readonly class OrganizationDetailsProvider
{
    public const string SETTING_KEY = 'organization_details';

    /** @var array<string, string> */
    private const array DEFAULTS = [
        'name' => 'Warsztatownia Sensoryczna',
        'street' => 'Aleja Jana Pawła II 12D',
        'postal_code' => '05-250',
        'city' => 'Radzymin',
        'email' => 'warsztatownia.sensoryczna@gmail.com',
        'phone' => '+48 571 531 213',
        'bank_account' => '46 2490 0005 0000 4000 1897 5420',
        'blik_phone' => '571 531 213',
    ];

    public function __construct(
        private SettingRepositoryInterface $settingRepository,
    ) {}

    public function get(): OrganizationDetails
    {
        $content = $this->settingRepository->findOneByKey(self::SETTING_KEY)?->getContent();
        $stored = is_array($content) ? $content : [];

        $value = static fn(string $key): string => is_string($stored[$key] ?? null) && trim($stored[$key]) !== ''
            ? trim($stored[$key])
            : self::DEFAULTS[$key];

        return new OrganizationDetails(
            name: $value('name'),
            street: $value('street'),
            postalCode: $value('postal_code'),
            city: $value('city'),
            email: $value('email'),
            phone: $value('phone'),
            bankAccount: $value('bank_account'),
            blikPhone: $value('blik_phone'),
        );
    }
}
