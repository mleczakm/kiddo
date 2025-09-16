<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Twig;

use App\Tenant\TenantContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TenantExtension extends AbstractExtension
{
    public function __construct(
        private readonly TenantContext $context
    ) {}

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant_phone', $this->phone(...)),
            new TwigFunction('tenant_account', $this->account(...)),
            new TwigFunction('tenant_name', $this->name(...)),
            new TwigFunction('tenant_email_from', $this->emailFrom(...)),
        ];
    }

    public function phone(): ?string
    {
        return $this->context->getBlikPhone();
    }

    public function account(): ?string
    {
        return $this->context->getTransferAccount();
    }

    public function name(): ?string
    {
        return $this->context->getTenant()?->getName();
    }

    public function emailFrom(): ?string
    {
        return $this->context->getEmailFrom();
    }
}
