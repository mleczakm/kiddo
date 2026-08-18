<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Serializer;

use Brick\Money\Money;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MoneyNormalizer implements DenormalizerInterface, NormalizerInterface
{
    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_array($data) || !is_string($data['amount'] ?? null) || !is_string($data['currency'] ?? null)) {
            throw new \InvalidArgumentException('Invalid data for Money denormalization');
        }

        return Money::of($data['amount'], $data['currency']);
    }

    #[\Override]
    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return (
            $type === Money::class
            && is_array($data)
            && is_string($data['amount'] ?? null)
            && is_string($data['currency'] ?? null)
        );
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            Money::class => true,
        ];
    }

    #[\Override]
    public function normalize(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): array|string|int|float|bool|\ArrayObject|null {
        if (!$data instanceof Money) {
            return null;
        }
        return [
            'amount' => $data->getAmount()->__toString(),
            'currency' => $data->getCurrency()->getCurrencyCode(),
        ];
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Money;
    }
}
