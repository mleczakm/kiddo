<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/** @extends PersistentObjectFactory<User> */
final class UserFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @return array<string, mixed>
     * @throws \OverflowException
     */
    #[\Override]
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'name' => self::faker()->name(),
        ];
    }
}
