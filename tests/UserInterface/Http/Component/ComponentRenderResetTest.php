<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\TwigComponent\ComponentRenderer;
use Symfony\UX\TwigComponent\Event\PreRenderEvent;

/**
 * Under Swoole every finished request runs the kernel.reset services while other requests may
 * still be rendering (they yield to the scheduler on I/O), all sharing one ComponentStack.
 * symfony/ux-twig-component 3.7 made ComponentRenderer::reset() empty that stack, so a
 * concurrent render then popped null and crashed the page ("Call to a member function
 * getName() on null", Sentry WARSZTATOWNIA-CY). This simulates the overlap: a reset in the
 * middle of a render must not break it.
 */
#[Group('functional')]
final class ComponentRenderResetTest extends KernelTestCase
{
    public function testResetDuringRenderDoesNotBreakTheInFlightComponent(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ComponentRenderer $renderer */
        $renderer = $container->get('ux.twig_component.component_renderer');
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');

        $resets = 0;
        $dispatcher->addListener(PreRenderEvent::class, static function () use ($renderer, &$resets): void {
            ++$resets;
            $renderer->reset(); // another request finishing while this one is mid-render
        });

        $html = $renderer->createAndRender('CapacityBadge', ['available' => 1, 'capacity' => 10, 'size' => 'sm']);

        static::assertSame(1, $resets, 'The simulated concurrent reset must have happened during the render');
        static::assertNotSame('', trim($html));
    }
}
