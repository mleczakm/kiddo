<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Scheduled sweep: anonymise every account whose deletion grace period has elapsed. */
final readonly class AnonymizeExpiredAccounts {}
