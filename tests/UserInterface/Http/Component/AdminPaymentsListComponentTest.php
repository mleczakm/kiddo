<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\AdminPaymentsListComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminPaymentsListComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRendersPaymentMethodWithoutCrashing(): void
    {
        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);

        $customer = UserAssembler::new()->assemble();
        $this->em->persist($customer);

        // PaymentAssembler doesn't accept an explicit method, so the Payment
        // constructor defaults it to PaymentMethod::ONLINE - a real enum instance,
        // not null. That's exactly what crashed
        // `{{ payment.method|default('transfer') }}` with "Object of class
        // App\Entity\PaymentMethod could not be converted to string", since Twig's
        // `default` filter only substitutes on empty values and leaves a non-empty
        // enum instance to be string-cast.
        $payment = PaymentAssembler::new()
            ->withUser($customer)
            ->assemble();
        $this->em->persist($payment);
        $this->em->flush();

        $this->client->loginUser($admin);

        $component = $this->createLiveComponent(name: AdminPaymentsListComponent::class, client: $this->client);
        $rendered = (string) $component->render();

        self::assertStringContainsString('Online', $rendered);
    }
}
