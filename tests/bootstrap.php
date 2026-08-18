<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;

require dirname(__DIR__) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__) . '/.env');

// bin/console and the real swoole server go through symfony/runtime, which installs an
// error handler before anything boots the kernel. Without one already registered,
// SwooleBundle::boot() hits ErrorHandler::register()'s `$handler->isRoot = true` branch on
// the *first* kernel boot in the process — but by then the handler it's registering is
// already a swoole-bundle stateful-service proxy (coroutines are enabled in every env, see
// config/packages/swoole.yaml), and writing a private property through that proxy is a
// fatal error. Installing a real handler first, like the real entry points do, makes
// ErrorHandler::register() take its "handler already present" branch instead, which never
// touches the property.
Debug::enable();
