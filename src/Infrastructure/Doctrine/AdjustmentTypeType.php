<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use App\Domain\Commerce\Pricing\AdjustmentType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Stores App\Domain\Commerce\Pricing\AdjustmentType as its plain string
 * value. XML-mapped entities in this codebase have no equivalent of
 * attribute-mapping's `enumType:` (the installed Doctrine ORM's XML schema
 * has no enum-type attribute), so PricingRule's adjustment type needs an
 * explicit custom type instead - matching the existing `ulid`/`lesson_map`
 * custom types registered in config/packages/doctrine.yaml.
 */
class AdjustmentTypeType extends StringType
{
    public const string NAME = 'adjustment_type_enum';

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?AdjustmentType
    {
        if ($value === null || $value === '') {
            return null;
        }

        return AdjustmentType::from((string) $value);
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof AdjustmentType ? $value->value : (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
