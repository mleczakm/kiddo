<?php

declare(strict_types=1);

namespace App\Application\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Type-safe extraction of a POST array field (e.g. files_id[], files_role[...]).
 * Symfony's ParameterBag::all() returns mixed per key; form submissions for
 * these fields are always string-keyed/string-valued in practice, so this
 * coerces rather than trusting that shape blindly.
 */
final readonly class RequestArrayField
{
    /**
     * @return list<string>
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     */
    public static function list(Request $request, string $key): array
    {
        $value = $request->request->all()[$key] ?? [];
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $v): string => (string) $v, $value));
    }

    /**
     * @return array<string, string>
     * @throws \Symfony\Component\HttpFoundation\Exception\BadRequestException
     */
    public static function map(Request $request, string $key): array
    {
        $value = $request->request->all()[$key] ?? [];
        if (!\is_array($value)) {
            return [];
        }

        $keys = array_map(static fn(mixed $k): string => (string) $k, array_keys($value));
        $values = array_map(static fn(mixed $v): string => (string) $v, array_values($value));

        return array_combine($keys, $values);
    }
}
