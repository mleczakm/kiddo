<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Api;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class AuthRateLimits
{
    public function __construct(
        #[Autowire(service: 'limiter.auth_email_limiter')]
        private RateLimiterFactory $authEmailRateLimiter,
        #[Autowire(service: 'limiter.auth_ip_limiter')]
        private RateLimiterFactory $authIpRateLimiter,
        #[Autowire(service: 'limiter.auth_verification_limiter')]
        private RateLimiterFactory $authVerificationRateLimiter,
    ) {}

    public function checkEmailLimit(string $email): ?JsonResponse
    {
        $limiter = $this->authEmailRateLimiter->create(hash('sha256', mb_strtolower(trim($email))));
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many code requests. Please try again later.',
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ], 429);
        }

        return null;
    }

    public function checkVerificationLimit(string $email): ?JsonResponse
    {
        $limit = $this->authVerificationRateLimiter->create(hash('sha256', mb_strtolower(trim($email))))->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many verification attempts. Please try again later.',
                'retry_after' => max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ], 429);
        }

        return null;
    }

    public function checkIpLimit(Request $request): ?JsonResponse
    {
        $limiter = $this->authIpRateLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many attempts. Please try again later.',
                'retry_after' => $limit->getRetryAfter()->getTimestamp() - time(),
            ], 429);
        }

        return null;
    }
}
