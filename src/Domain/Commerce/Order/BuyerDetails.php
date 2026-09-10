<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Order;

final readonly class BuyerDetails
{
    private function __construct(
        public BuyerType $type,
        public ?string $taxIdentifier,
    ) {}

    public static function privateCustomer(): self
    {
        return new self(BuyerType::PRIVATE, null);
    }

    /** @throws \InvalidArgumentException */
    public static function company(string $taxIdentifier): self
    {
        $taxIdentifier = preg_replace('/\D+/', '', $taxIdentifier) ?? '';
        if (!self::isValidPolishNip($taxIdentifier)) {
            throw new \InvalidArgumentException('A valid Polish NIP is required for company purchases.');
        }

        return new self(BuyerType::COMPANY, $taxIdentifier);
    }

    /** @throws \InvalidArgumentException */
    public static function fromInput(string $type, ?string $taxIdentifier): self
    {
        return match (BuyerType::tryFrom($type)) {
            BuyerType::PRIVATE => self::privateCustomer(),
            BuyerType::COMPANY => self::company($taxIdentifier ?? ''),
            null => throw new \InvalidArgumentException('Unknown buyer type.'),
        };
    }

    private static function isValidPolishNip(string $nip): bool
    {
        if (preg_match('/^\d{10}$/', $nip) !== 1) {
            return false;
        }

        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (int) $nip[$index] * $weight;
        }

        return ($sum % 11) === (int) $nip[9];
    }
}
