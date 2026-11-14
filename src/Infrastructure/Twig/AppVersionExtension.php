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
        $release = \DateTimeImmutable::createFromFormat('YmdHi', $appVersion);
        if ($release === false) {
            throw new \InvalidArgumentException('Invalid app version format: ' . $appVersion);
        }

        $this->lastReleaseTime = $release;
    }

    #[AsTwigFunction('last_release')]
    public function lastRelease(): \DateTimeImmutable
    {
        return $this->lastReleaseTime;
    }
}
