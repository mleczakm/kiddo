<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    /** @var string $environment */
    $environment = $context['APP_ENV'];

    return new Kernel($environment, (bool) $context['APP_DEBUG']);
};
