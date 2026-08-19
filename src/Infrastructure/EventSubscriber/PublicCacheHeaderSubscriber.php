<?php

declare(strict_types=1);

namespace App\Infrastructure\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Symfony's AbstractSessionListener forces every response to
 * `private, max-age=0, must-revalidate` once the session was touched during
 * the request — true for effectively all requests here, since the
 * remember_me firewall reads the session on every hit to check for a
 * cookie. That's the right default for session-tied content, but it also
 * silently defeats an explicit `public, max-age=...` set on genuinely
 * stateless public routes (article list/detail, public file delivery),
 * which render identically regardless of session state.
 *
 * Runs after AbstractSessionListener (priority -1000) so its override
 * lands last. Routes opt in by stashing their intended max-age in a
 * throwaway X-Cache-Public-Max-Age header instead of calling setPublic()/
 * setMaxAge() directly, since that would just get clobbered by the session
 * listener anyway.
 */
final class PublicCacheHeaderSubscriber
{
    private const string MARKER_HEADER = 'X-Cache-Public-Max-Age';

    #[AsEventListener(event: ResponseEvent::class, priority: -1024)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $maxAge = $response->headers->get(self::MARKER_HEADER);
        if ($maxAge === null) {
            return;
        }

        $response->headers->remove(self::MARKER_HEADER);
        $response->setPublic();
        $response->setMaxAge((int) $maxAge);
    }
}
