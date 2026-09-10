<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\RevokeConsent;
use App\Application\Consent\ConsentRecorder;

final readonly class RevokeConsentHandler
{
    public function __construct(
        private ConsentRecorder $consentRecorder,
    ) {}

    /** @throws \UnexpectedValueException */
    public function __invoke(RevokeConsent $command): void
    {
        $this->consentRecorder->revoke($command->user, $command->type, $command->source);
    }
}
