<?php

declare(strict_types=1);

namespace App\Tests\Application\Newsletter;

use App\Application\Newsletter\NewsletterSubscriptionManager;
use App\Entity\User;
use App\Infrastructure\Brevo\BrevoNewsletterService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
final class NewsletterSubscriptionManagerTest extends TestCase
{
    public function testFalseToTrueSubscribesAndCallsBrevo(): void
    {
        $user = new User('someone@example.com', 'Alice');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::once())
            ->method('addOrUpdateContact')
            ->with('someone@example.com', 'Alice');
        $brevo->expects(self::never())
            ->method('removeContactFromList');

        $manager = new NewsletterSubscriptionManager($brevo);
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: true);

        self::assertTrue($user->isNewsletterSubscribed());
        self::assertNotNull($user->getNewsletterConsentDate());
    }

    public function testTrueToFalseUnsubscribesAndCallsBrevo(): void
    {
        $user = new User('gone@example.com', 'Bob');
        $user->setNewsletterSubscribed(true);
        $user->setNewsletterConsentDate(new \DateTimeImmutable('2025-01-01T00:00:00Z'));

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::never())
            ->method('addOrUpdateContact');
        $brevo->expects(self::once())
            ->method('removeContactFromList')
            ->with('gone@example.com');

        $manager = new NewsletterSubscriptionManager($brevo);
        $manager->applyTransition($user, wasSubscribed: true, desiredSubscribed: false);

        self::assertFalse($user->isNewsletterSubscribed());
        self::assertNull($user->getNewsletterConsentDate());
    }

    public function testNoChangeSkipsBrevo(): void
    {
        $user = new User('same@example.com', 'Carol');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->expects(self::never())
            ->method('addOrUpdateContact');
        $brevo->expects(self::never())
            ->method('removeContactFromList');

        $manager = new NewsletterSubscriptionManager($brevo);
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: false);

        self::assertFalse($user->isNewsletterSubscribed());
    }

    public function testBrevoFailureIsLoggedButStateStillApplied(): void
    {
        $user = new User('boom@example.com', 'Dan');

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('addOrUpdateContact')
            ->willThrowException(new \RuntimeException('Brevo down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Failed to add contact'),
                self::callback(fn(array $context) => ($context['email'] ?? null) === 'boom@example.com'),
            );

        $manager = new NewsletterSubscriptionManager($brevo, $logger);
        $manager->applyTransition($user, wasSubscribed: false, desiredSubscribed: true);

        self::assertTrue($user->isNewsletterSubscribed());
        self::assertNotNull($user->getNewsletterConsentDate());
    }

    public function testBrevoRemovalFailureIsLoggedButStateStillApplied(): void
    {
        $user = new User('boom@example.com', 'Dan');
        $user->setNewsletterSubscribed(true);
        $user->setNewsletterConsentDate(new \DateTimeImmutable('2025-01-01T00:00:00Z'));

        $brevo = $this->createMock(BrevoNewsletterService::class);
        $brevo->method('removeContactFromList')
            ->willThrowException(new \RuntimeException('Brevo down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Failed to remove contact'), self::anything());

        $manager = new NewsletterSubscriptionManager($brevo, $logger);
        $manager->applyTransition($user, wasSubscribed: true, desiredSubscribed: false);

        self::assertFalse($user->isNewsletterSubscribed());
        self::assertNull($user->getNewsletterConsentDate());
    }
}
