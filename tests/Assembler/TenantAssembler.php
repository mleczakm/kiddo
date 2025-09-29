<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Tenant;

/**
 * Simple builder for Tenant entity for tests.
 */
final class TenantAssembler
{
    private string $name = 'Tenant ';

    private ?string $domain = null;

    private ?string $emailFrom = null;

    private ?string $blikPhone = null;

    private ?string $transferAccount = null;

    private function __construct() {}

    public static function new(): self
    {
        $i = new self();
        $i->name = 'Tenant ' . substr(uniqid('', true), -5);
        $i->domain = 'tenant' . random_int(100, 999) . '.test';
        return $i;
    }

    public function withName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function withDomain(?string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    public function withEmailFrom(?string $email): self
    {
        $this->emailFrom = $email;
        return $this;
    }

    public function withBlikPhone(?string $phone): self
    {
        $this->blikPhone = $phone;
        return $this;
    }

    public function withTransferAccount(?string $account): self
    {
        $this->transferAccount = $account;
        return $this;
    }

    public function assemble(): Tenant
    {
        return new Tenant($this->name, $this->domain, $this->emailFrom, $this->blikPhone, $this->transferAccount);
    }
}
