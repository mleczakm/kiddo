<?php

declare(strict_types=1);

namespace App\Tests\Application\Newsletter;

use App\Application\Newsletter\NewsletterSubscriptionManager;
use App\Application\Service\ActivityLogger;
use App\Entity\User;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Group('unit')]
final class NewsletterSubscriptionManagerTest extends TestCase
{
    private function activityLogger(): ActivityLogger
    {
        return new ActivityLogger($this->createMock(EventDispatcherInterface::class));
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        return $this->createMock(UrlGeneratorInterface::class);
    }

    public function testFalseToTrueSubscribesAndCallsBrevo(): void
    {
        $user = new User('someone@example.com', 'Alice');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::once())->method('addOrUpdateContact')->with('someone@example.com', 'Alice');
        $brevo->expects(self::never())->method('removeContactFromList');

        $manager = new NewsletterSubscriptionManager($brevo, $this->activityLogger(), $this->urlGenerator());
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: true);

        static::assertTrue($user->isNewsletterSubscribed());
        static::assertNotNull($user->getNewsletterConsentDate());
    }

    public function testTrueToFalseUnsubscribesAndCallsBrevo(): void
    {
        $user = new User('gone@example.com', 'Bob');
        $user->setNewsletterSubscribed(true);
        $user->setNewsletterConsentDate(new \DateTimeImmutable('2025-01-01T00:00:00Z'));

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::never())->method('addOrUpdateContact');
        $brevo->expects(self::once())->method('removeContactFromList')->with('gone@example.com');

        $manager = new NewsletterSubscriptionManager($brevo, $this->activityLogger(), $this->urlGenerator());
        $manager->applyTransition($user, wasSubscribed: true, desiredSubscribed: false);

        static::assertFalse($user->isNewsletterSubscribed());
        static::assertNull($user->getNewsletterConsentDate());
    }

    public function testNoChangeSkipsBrevo(): void
    {
        $user = new User('same@example.com', 'Carol');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::never())->method('addOrUpdateContact');
        $brevo->expects(self::never())->method('removeContactFromList');

        $manager = new NewsletterSubscriptionManager($brevo, $this->activityLogger(), $this->urlGenerator());
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: false);

        static::assertFalse($user->isNewsletterSubscribed());
    }

    public function testBrevoFailureIsLoggedButStateStillApplied(): void
    {
        $user = new User('boom@example.com', 'Dan');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('addOrUpdateContact')->willThrowException(new \RuntimeException('Brevo down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                static::stringContains('Failed to add contact'),
                static::callback(static fn(array $context) => ($context['email'] ?? null) === 'boom@example.com'),
            );

        $manager = new NewsletterSubscriptionManager($brevo, $this->activityLogger(), $this->urlGenerator(), $logger);
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: true);

        static::assertTrue($user->isNewsletterSubscribed());
        static::assertNotNull($user->getNewsletterConsentDate());
    }

    public function testBrevoRemovalFailureIsLoggedButStateStillApplied(): void
    {
        $user = new User('boom@example.com', 'Dan');
        $user->setNewsletterSubscribed(true);
        $user->setNewsletterConsentDate(new \DateTimeImmutable('2025-01-01T00:00:00Z'));

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('removeContactFromList')->willThrowException(new \RuntimeException('Brevo down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(static::stringContains('Failed to remove contact'), static::anything());

        $manager = new NewsletterSubscriptionManager($brevo, $this->activityLogger(), $this->urlGenerator(), $logger);
        $manager->applyTransition($user, wasSubscribed: true, desiredSubscribed: false);

        static::assertFalse($user->isNewsletterSubscribed());
        static::assertNull($user->getNewsletterConsentDate());
    }
}
