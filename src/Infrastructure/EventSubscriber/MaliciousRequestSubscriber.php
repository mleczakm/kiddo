<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use App\Infrastructure\Security\HoneypotResponder;
use App\Infrastructure\Security\MaliciousRequestPathMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MaliciousRequestSubscriber
{
    private const int REQUEST_PRIORITY = 512;

    private const int EXCEPTION_PRIORITY = 512;

    public function __construct(
        private MaliciousRequestPathMatcher $pathMatcher,
        private HoneypotResponder $honeypotResponder,
    ) {}

    #[AsEventListener(event: 'kernel.request', priority: self::REQUEST_PRIORITY)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if (! $this->pathMatcher->matches($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->setResponse($this->honeypotResponder->createResponse());
    }

    #[AsEventListener(event: 'kernel.exception', priority: self::EXCEPTION_PRIORITY)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if (! $event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        if (! $this->pathMatcher->matches($event->getRequest()->getPathInfo())) {
            return;
        }

        $event->setResponse($this->honeypotResponder->createResponse());
        $event->allowCustomResponseCode();
        $event->stopPropagation();
    }
}
