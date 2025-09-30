<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class InstanceofExtension extends AbstractExtension
{
    /**
     * @return TwigTest[]
     */
    #[\Override]
    public function getTests(): array
    {
        return [new TwigTest('instanceof', $this->isInstanceof(...))];
    }

    public function isInstanceof(mixed $var, string|object $instance): bool
    {
        return $var instanceof $instance;
    }
}
