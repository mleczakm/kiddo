<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[Group('functional')]
final class DashboardMonthNavigationTest extends WebTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testPastMonthShowsItsOwnKpisAndPlatformFee(): void
    {
        Clock::set(new MockClock('2025-03-15 12:00:00'));
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = UserAssembler::new()->withEmail('admin@test.com')->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->persist(
            PaymentAssembler::new()
                ->withUser($admin)
                ->withAmount(Money::of(1000, 'PLN'))
                ->withStatus(Payment::STATUS_PAID)
                ->withCreatedAt(new \DateTimeImmutable('2025-02-05'))
                ->assemble(),
        );
        $em->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin?miesiac=2025-02');

        $this->assertResponseIsSuccessful();
        $nav = $crawler->filter('[data-testid="month-nav"]')->text();
        static::assertStringContainsString('Luty 2025', $nav);
        static::assertStringContainsString('Bieżący miesiąc', $nav);
        static::assertCount(0, $crawler->filter('a[href^="/admin/raport-miesieczny"]'));
        $page = $crawler->text();
        static::assertStringContainsString('Kwota za luty', $page);
        static::assertStringContainsString('20.00 zł', $page);
        static::assertStringNotContainsString('Prognoza za bieżący miesiąc', $page);
        static::assertSame('/admin?miesiac=2025-01', $crawler->filter('a[rel="prev"]')->attr('href'));
        static::assertSame('/admin?miesiac=2025-03', $crawler->filter('a[rel="next"]')->attr('href'));
    }

    public function testCurrentMonthKeepsTheForecastLabelAndInvalidMonthFallsBack(): void
    {
        Clock::set(new MockClock('2025-03-15 12:00:00'));
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = UserAssembler::new()->withEmail('admin@test.com')->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin?miesiac=bogus');

        $this->assertResponseIsSuccessful();
        static::assertStringContainsString('Marzec 2025', $crawler->filter('[data-testid="month-nav"]')->text());
        static::assertStringContainsString('Prognoza za bieżący miesiąc', $crawler->text());
    }

    public function testSuperAdminSeesPrinterIconLinkingToTheDisplayedMonthsReport(): void
    {
        Clock::set(new MockClock('2025-03-15 12:00:00'));
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $super = UserAssembler::new()->withEmail('super@test.com')->withRoles('ROLE_SUPER_ADMIN')->assemble();
        $em->persist($super);
        $em->flush();
        $client->loginUser($super);

        $crawler = $client->request('GET', '/admin?miesiac=2025-02');

        $link = $crawler->filter('[data-testid="month-nav"] a[href^="/admin/raport-miesieczny"]');
        static::assertCount(1, $link);
        static::assertSame('/admin/raport-miesieczny/2025-02', $link->attr('href'));
        static::assertSame('Zestawienie miesięczne do druku', $link->attr('aria-label'));
        static::assertCount(1, $link->filter('svg'));
    }
}
