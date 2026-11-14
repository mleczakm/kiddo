<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

readonly class AppVersionExtension
{
    private \DateTimeImmutable $lastReleaseTime;

    /** @throws \InvalidArgumentException */
    public function __construct(string $appVersion)
    {
        $this->lastReleaseTime = \DateTimeImmutable::createFromFormat('YmdHi', $appVersion)
        ?: throw new \InvalidArgumentException('Invalid app version format: ' . $appVersion);
    }

    #[AsTwigFunction('last_release')]
    public function lastRelease(): \DateTimeImmutable
    {
        return $this->lastReleaseTime;
    }
}
