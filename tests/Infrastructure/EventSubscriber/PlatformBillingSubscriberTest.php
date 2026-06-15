<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\EventSubscriber;

use App\Entity\Payment;
use App\Entity\Setting;
use App\Infrastructure\EventSubscriber\PlatformBillingSubscriber;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Event\Event;

#[Group('functional')]
class PlatformBillingSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private PlatformBillingSubscriber $subscriber;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->subscriber = self::getContainer()->get(PlatformBillingSubscriber::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = PlatformBillingSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.payment.transition.pay', $events);
        $this->assertEquals('onPaymentPaid', $events['workflow.payment.transition.pay']);
    }

    public function testOnPaymentPaidAddsCommission(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();

        // Set up initial billing data
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        // Create a payment
        $user = UserAssembler::new()->assemble();
        $payment = new Payment($user, Money::of('1000.00', 'PLN'));

        // Create event with payment as subject
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn($payment);

        // Call the subscriber
        $this->subscriber->onPaymentPaid($event);

        // Verify billing data was updated
        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('120.00', $updatedContent['currentDue']); // 100 + (1000 * 0.02) = 120
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }

    public function testOnPaymentPaidDoesNothingWhenSubjectIsNotPayment(): void
    {
        $this->entityManager->getConnection()
            ->beginTransaction();

        // Set up initial billing data
        $setting = new Setting();
        $setting->setKey('platform_billing');
        $setting->setContent([
            'currentDue' => '100.00',
            'pastDue' => '0.00',
        ]);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();

        // Create event with non-payment subject
        $event = $this->createMock(Event::class);
        $event->method('getSubject')
            ->willReturn(new \stdClass());

        // Call the subscriber
        $this->subscriber->onPaymentPaid($event);

        // Verify billing data was NOT updated
        $this->entityManager->refresh($setting);
        $updatedContent = $setting->getContent();
        /** @var array{currentDue: string, pastDue: string} $updatedContent */
        $this->assertEquals('100.00', $updatedContent['currentDue']);
        $this->assertEquals('0.00', $updatedContent['pastDue']);
    }
}
