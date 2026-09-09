<?php

declare(strict_types=1);

namespace App\Application\Consent;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class RequestContext
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function ip(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }

    public function userAgent(): ?string
    {
        $userAgent = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 1000);
    }
}
