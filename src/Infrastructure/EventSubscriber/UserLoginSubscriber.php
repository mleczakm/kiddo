<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Application\Command\Notification\NewUser;
use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

final readonly class UserLoginSubscriber
{
    public function __construct(
        private MessageBusInterface $bus
    ) {}

    #[AsEventListener(event: InteractiveLoginEvent::class)]
    public function onUserLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()
            ->getUser();
        if ($user instanceof User && $user->getConfirmedAt() === null) {
            $this->bus->dispatch(new NewUser($user));
        }
    }
}
