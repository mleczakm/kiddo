<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentRecorder;

final readonly class RecordConsentsHandler
{
    public function __construct(
        private ConsentRecorder $consentRecorder,
    ) {}

    /** @throws \InvalidArgumentException */
    public function __invoke(RecordConsents $command): void
    {
        $this->consentRecorder->recordMany($command->user, $command->source, ...$command->grants);
    }
}
