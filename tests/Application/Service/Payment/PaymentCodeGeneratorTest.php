<?php

declare(strict_types=1);

namespace App\Tests\Application\Service\Payment;

use App\Application\Service\Payment\PaymentCodeGenerator;
use App\Entity\PaymentCode;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Stage 6: code generation lives here (previously duplicated between
 * PaymentCode::generateCode() and the now-deleted PaymentFactory::generateCode()),
 * checking availability before committing to a code rather than catching a
 * unique-constraint violation after the fact.
 */
#[Group('functional')]
final class PaymentCodeGeneratorTest extends KernelTestCase
{
    private PaymentCodeGenerator $generator;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        /** @var PaymentCodeGenerator $generator */
        $generator = self::getContainer()->get(PaymentCodeGenerator::class);
        $this->generator = $generator;
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
    }

    public function testGenerateProducesAFourCharacterCode(): void
    {
        $code = $this->generator->generate();

        static::assertMatchesRegularExpression('/^[' . preg_quote(PaymentCode::CHARS, '/') . ']{4}$/', $code);
    }

    public function testGenerateAvailableNeverReturnsAnAlreadyTakenCode(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->em->persist($user);
        $payment = PaymentAssembler::new()->withUser($user)->assemble();
        $this->em->persist($payment);
        $taken = new PaymentCode($payment, 'ABCD');
        $this->em->persist($taken);
        $this->em->flush();

        for ($i = 0; $i < 20; $i++) {
            static::assertNotSame('ABCD', $this->generator->generateAvailable());
        }
    }

    public function testCreateForPersistsAWorkingPaymentCode(): void
    {
        $user = UserAssembler::new()->assemble();
        $this->em->persist($user);
        $payment = PaymentAssembler::new()->withUser($user)->assemble();
        $this->em->persist($payment);
        $this->em->flush();

        $paymentCode = $this->generator->createFor($payment);

        static::assertSame(4, strlen($paymentCode->getCode()));
        static::assertSame($payment, $paymentCode->getPayment());
        static::assertSame($paymentCode, $payment->getPaymentCode());

        $this->em->clear();
        $reloaded = $this->em->getRepository(PaymentCode::class)->findOneBy(['code' => $paymentCode->getCode()]);
        static::assertNotNull($reloaded);
    }
}
