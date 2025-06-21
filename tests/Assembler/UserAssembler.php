<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\User;

/**
 * @extends EntityAssembler<User>
 */
class UserAssembler extends EntityAssembler
{
    public function withId(int $id): static
    {
        return $this->with('id', $id);
    }

    public function withEmail(string $email): static
    {
        return $this->with('email', $email);
    }

    public function withName(string $name): static
    {
        return $this->with('name', $name);
    }

    /**
     * @param array<string> $roles
     */
    public function withRoles(array $roles): static
    {
        return $this->with('roles', $roles);
    }

    public function withPhone(?string $phone): static
    {
        return $this->with('phone', $phone);
    }

    public function assemble(): User
    {
        $user = new User();

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($user);
            $property = $reflection->getProperty('id');
            $property->setAccessible(true);
            $property->setValue($user, $this->properties['id']);
        }

        foreach ($this->properties as $property => $value) {
            if ($property !== 'id') {
                $method = 'set' . ucfirst($property);
                if (method_exists($user, $method)) {
                    $user->{$method}($value);
                }
            }
        }

        return $user;
    }
}
