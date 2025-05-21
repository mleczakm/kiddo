<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use Brick\Money\Money;

class MoneyNormalizer implements \Symfony\Component\Serializer\Normalizer\DenormalizerInterface, \Symfony\Component\Serializer\Normalizer\NormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (! is_array($data)
            || ! isset($data['amount'], $data['currency'])
            || ! is_string($data['amount'])
            || ! is_string($data['currency'])
        ) {
            throw new \InvalidArgumentException('Invalid data for Money denormalization');
        }

        return Money::of($data['amount'], $data['currency']);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === Money::class
            && is_array($data)
            && isset($data['amount'], $data['currency'])
            && is_string($data['amount'])
            && is_string($data['currency']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Money::class => true,
        ];
    }

    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|\ArrayObject|null {
        if (! $data instanceof Money) {
            return null;
        }
        return [
            'amount' => $data->getAmount()
                ->__toString(),
            'currency' => $data->getCurrency()
                ->getCurrencyCode(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Money;
    }
}
