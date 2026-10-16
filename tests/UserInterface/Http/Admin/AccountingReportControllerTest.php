<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\FinanceContact;
use App\Entity\Payment;
use App\Entity\User;
use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class AccountingReportControllerTest extends WebTestCase
{
    public function testAnonymousUserIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/raport-miesieczny/2025-03');

        $this->assertResponseRedirects();
    }

    public function testPlainAdminIsDenied(): void
    {
        $client = static::createClient();
        $client->loginUser($this->persistUser($client, 'admin@test.com', 'ROLE_ADMIN'));

        $client->request('GET', '/admin/raport-miesieczny/2025-03');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testFinanceContactCanOpenReport(): void
    {
        $client = static::createClient();
        $user = $this->persistUser($client, 'finance@test.com', 'ROLE_ADMIN');
        $em = $this->em($client);
        $em->persist(new FinanceContact($user));
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/admin/raport-miesieczny/2025-03');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Marzec 2025');
    }

    public function testSuperAdminSeesRevenueSplitPerLesson(): void
    {
        $client = static::createClient();
        $super = $this->persistUser($client, 'super@test.com', 'ROLE_SUPER_ADMIN');
        $em = $this->em($client);
        $customer = $this->persistUser($client, 'customer@test.com', 'ROLE_USER');

        $lessonA = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Ceramika A')->assemble())
            ->withSchedule(new \DateTimeImmutable('2025-03-20 10:00:00'))
            ->assemble();
        $lessonB = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Rzeźba B')->assemble())
            ->withSchedule(new \DateTimeImmutable('2025-03-22 10:00:00'))
            ->assemble();
        $em->persist($lessonA);
        $em->persist($lessonB);

        // 100 zł single-lesson payment, 60 zł payment covering two lessons.
        $single = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(100, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-05'))
            ->assemble();
        $double = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(60, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable('2025-03-06'))
            ->assemble();
        $outside = PaymentAssembler::new()
            ->withUser($customer)
            ->withAmount(Money::of(999, 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable('2025-02-06'))
            ->assemble();
        foreach ([$single, $double, $outside] as $payment) {
            $em->persist($payment);
        }
        $em->persist(
            BookingAssembler::new()->withUser($customer)->withPayment($single)->withLessons($lessonA)->assemble(),
        );
        $em->persist(
            BookingAssembler::new()
                ->withUser($customer)
                ->withPayment($double)
                ->withLessons($lessonA, $lessonB)
                ->assemble(),
        );
        $em->flush();
        $em->clear();

        $client->loginUser($super);
        $crawler = $client->request('GET', '/admin/raport-miesieczny/2025-03');

        $this->assertResponseIsSuccessful();
        $rows = $crawler
            ->filter('tbody tr')
            ->each(static fn($row): string => preg_replace('/\s+/u', ' ', trim($row->text())) ?? '');
        static::assertCount(2, $rows);
        static::assertStringContainsString('Ceramika A', $rows[0]);
        static::assertStringContainsString('130', $rows[0]);
        static::assertStringContainsString('Rzeźba B', $rows[1]);
        static::assertStringContainsString('30', $rows[1]);
        static::assertStringContainsString('160', $crawler->filter('tfoot')->text());
    }

    private function em(KernelBrowser $client): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    private function persistUser(KernelBrowser $client, string $email, string $role): User
    {
        $user = UserAssembler::new()->withEmail($email)->withRoles($role)->assemble();
        $em = $this->em($client);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
