<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\Payment;
use App\Entity\Transfer;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AssignPaymentModalComponent;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AssignPaymentModalComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;

        $admin = UserAssembler::new()->withEmail('admin@example.com')->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    /**
     * Regression: the modal is rendered once per pending transfer. If that
     * transfer is rejected (hard-deleted) before a later action re-hydrates
     * this component, hydration used to assign null into a non-nullable
     * Transfer property and 500 with a PropertyAccess InvalidTypeException.
     */
    public function testActionDoesNotFailWhenTheTransferWasRejectedMeanwhile(): void
    {
        $transfer = TransferAssembler::new()->withSender('Jan Kowalski')->assemble();
        $this->em->persist($transfer);
        $this->em->flush();

        $component = $this->createLiveComponent(
            name: AssignPaymentModalComponent::class,
            data: [
                'transfer' => $transfer,
            ],
            client: $this->client,
        );

        // Someone rejects the transfer from the list in the meantime.
        $this->em->remove($transfer);
        $this->em->flush();

        // Previously threw during hydration, before the action ran at all.
        $rendered = (string) $component->call('openModal')->render();

        static::assertStringContainsString('data-live-action-param="openModal"', $rendered);
        static::assertStringNotContainsString('Jan Kowalski', $rendered);
    }

    public function testConfirmAssignmentLinksTheSelectedPaymentAndMarksItPaid(): void
    {
        $customer = UserAssembler::new()->withEmail('customer@example.com')->assemble();
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $transfer = TransferAssembler::new()->withAmount('100.00')->assemble();

        $this->em->persist($customer);
        $this->em->persist($payment);
        $this->em->persist($transfer);
        $this->em->flush();

        $paymentId = $payment->getId();
        $transferId = $transfer->getId();
        static::assertNotNull($transferId);

        $component = $this->createLiveComponent(
            name: AssignPaymentModalComponent::class,
            data: [
                'transfer' => $transfer,
            ],
            client: $this->client,
        );

        $component->call('openModal');
        $component->call('selectPayment', [
            'paymentId' => (string) $paymentId,
        ]);
        $component->call('confirmAssignment');

        $this->em->clear();

        $reloadedPayment = $this->em->find(Payment::class, $paymentId);
        static::assertInstanceOf(Payment::class, $reloadedPayment);
        static::assertSame(Payment::STATUS_PAID, $reloadedPayment->getStatus());

        $reloadedTransfer = $this->em->find(Transfer::class, $transferId);
        static::assertInstanceOf(Transfer::class, $reloadedTransfer);
        static::assertNotNull($reloadedTransfer->getPayment());

        // Modal closed and its selection was reset.
        $rendered = (string) $component->render();
        static::assertStringContainsString('data-live-action-param="openModal"', $rendered);
    }
}
