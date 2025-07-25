<?php

declare(strict_types=1);

namespace App\Tests\Util;

use Symfony\Component\Messenger\Envelope;

class MessengerFake implements \Symfony\Component\Messenger\MessageBusInterface
{
    /**
     * @var Envelope[]
     */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->dispatched[] = new Envelope($message, $stamps);
    }
}
