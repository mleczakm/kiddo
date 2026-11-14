<?php

declare(strict_types=1);

namespace App\Application\Chat;

/** @internal */
final class ChatTokenPayload
{
    /** @throws \InvalidArgumentException */
    public static function decode(string $json): ChatToken
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (
            !is_array($decoded)
            || ($decoded['exp'] ?? null) === null
            || ($decoded['jti'] ?? null) === null
            || !is_array($decoded['roles'] ?? null)
        ) {
            throw new \InvalidArgumentException('Invalid chat token payload');
        }

        $roles = array_values(array_filter($decoded['roles'], is_string(...)));

        /** @var mixed $uidRaw */
        $uidRaw = $decoded['uid'] ?? null;
        $isGuest = ($decoded['guest'] ?? false) === true || in_array($uidRaw, [null, 0], true);
        $userId = $isGuest ? null : (int) $uidRaw;

        return new ChatToken(
            userId: $userId,
            roles: $roles,
            expiresAt: new \DateTimeImmutable()->setTimestamp((int) $decoded['exp']),
            jti: (string) $decoded['jti'],
        );
    }
}
