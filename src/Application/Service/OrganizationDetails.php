<?php

declare(strict_types=1);

namespace App\Application\Service;

/**
 * The organisation's public contact + payment details, as shown in the panel
 * sidebar footer and the BLIK/transfer payment instructions. Admin-editable
 * (see AdminSettingsComponent); OrganizationDetailsProvider fills any unset
 * field with a sensible default.
 *
 * $facebookUrl has no default: it is an empty string until an admin sets it,
 * and clearing the admin field switches the Facebook integration back off.
 */
final readonly class OrganizationDetails
{
    public function __construct(
        public string $name,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $email,
        public string $phone,
        public string $bankAccount,
        public string $blikPhone,
        public string $facebookUrl,
    ) {}

    public function addressLine(): string
    {
        return trim(sprintf('%s, %s %s', $this->street, $this->postalCode, $this->city), ', ');
    }
}
