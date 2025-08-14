<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class AccessDeniedHandler implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // the priority must be greater than the Security HTTP
            // ExceptionListener, to make sure it's called before
            // the default exception listener
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (! $exception instanceof AccessDeniedException) {
            return;
        }

        $path = $event->getRequest()
            ->getPathInfo();

        if (! str_starts_with($path, $this->router->generate('dashboard')) && ! str_starts_with(
            $path,
            $this->router->generate('app_admin_dashboard')
        )) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('app_login')));
    }
}
