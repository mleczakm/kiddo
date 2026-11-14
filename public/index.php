<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    /** @var string $environment */
    $environment = $context['APP_ENV'];

    /** @var bool $debug */
    $debug = $context['APP_DEBUG'];

    return new Kernel($environment, $debug);
};
