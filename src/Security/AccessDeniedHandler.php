<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class AccessDeniedHandler implements EventSubscriberInterface
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private Security $security,
    ) {}

    #[\Override]
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

        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        // Only bounce genuinely unauthenticated visitors to the login page.
        // A logged-in user who simply lacks the required role should see the
        // normal 403 response, not be silently redirected back to /login.
        if ($this->security->getUser() !== null) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (
            !str_starts_with($path, $this->router->generate('dashboard'))
            && !str_starts_with($path, $this->router->generate('app_admin_dashboard'))
        ) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('app_login')));
    }
}
