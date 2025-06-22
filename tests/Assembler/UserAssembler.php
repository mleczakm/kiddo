<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\User;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;

class UserAssembler
{
    private ?int $id = null;

    private string $email;

    private string $name;

    private ?PhoneNumber $phone = null;

    /**
     * @var list<string>
     */
    private array $roles = ['ROLE_USER'];

    private ?\DateTimeImmutable $createdAt = null;

    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct() {}

    public static function new(): self
    {
        $instance = new self();
        $instance->email = 'user' . uniqid('', true) . '@example.com';
        $instance->name = 'Test User';
        return $instance;
    }

    public function withId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withRoles(string ... $roles): self
    {
        $this->roles = array_values($roles);

        return $this;
    }

    public function withPhone(?string $phone): self
    {
        if ($phone !== null) {
            $phoneUtil = PhoneNumberUtil::getInstance();
            try {
                $this->phone = $phoneUtil->parse($phone, 'PL');
            } catch (\Exception) {
                // If parsing fails, set to null
                $this->phone = null;
            }
        } else {
            $this->phone = null;
        }

        return $this;
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function withUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function assemble(): User
    {
        $user = new User();

        // Set ID using reflection if provided
        if ($this->id !== null) {
            $reflection = new \ReflectionClass($user);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($user, $this->id);
        }

        // Set properties using public methods
        $user->setEmail($this->email);
        $user->setName($this->name);
        $user->setRoles($this->roles);

        if ($this->phone !== null) {
            $user->setPhone($this->phone);
        }

        // Set timestamps if provided, otherwise let the entity handle them
        if ($this->createdAt !== null) {
            $createdAtProperty = new \ReflectionClass($user)
                ->getProperty('createdAt');
            $createdAtProperty->setAccessible(true);
            $createdAtProperty->setValue($user, $this->createdAt);
        }

        if ($this->updatedAt !== null) {
            $user->setUpdatedAt($this->updatedAt);
        }

        return $user;
    }
}
