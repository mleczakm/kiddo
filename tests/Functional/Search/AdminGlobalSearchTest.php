<?php

declare(strict_types=1);

namespace App\Tests\Functional\Search;

use App\Application\Search\GlobalSearchQuery;
use App\Application\Search\SearchReference;
use App\Application\Search\SearchType;
use App\Entity\AgeRange;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\PaymentCode;
use App\Entity\User;
use App\Infrastructure\Search\SearchResultHydrator;
use Brick\Money\Money;
use App\UserInterface\Http\Component\AdminGlobalSearchComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class AdminGlobalSearchTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $entityManager;

    private User $admin;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->browser = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->admin = new User('search-admin@example.test', 'Search Admin');
        $this->admin->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($this->admin);
    }

    public function testDatabaseQuerySearchesAcrossTypesAndOrdersExactMatchFirst(): void
    {
        $needle = 'Zefir' . bin2hex(random_bytes(4));
        $client = new User(mb_strtolower($needle) . '@example.test', $needle);
        $this->entityManager->persist($client);
        $lesson = $this->lesson($needle . ' eksperymenty');
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $search = self::getContainer()->get(GlobalSearchQuery::class);
        $references = array_values(iterator_to_array($search->search($needle)));

        self::assertNotEmpty($references);
        self::assertSame(SearchType::Client, $references[0]->type);
        self::assertSame((string) $client->getId(), $references[0]->id);
        self::assertTrue($this->contains($references, SearchType::Lesson, $lesson->getId()->toRfc4122()));

        $results = self::getContainer()->get(SearchResultHydrator::class)->hydrate($references);
        $resultTypes = array_map(static fn($result): SearchType => $result->reference->type, $results);
        self::assertContains(SearchType::Lesson, $resultTypes);
    }

    public function testSearchesPaymentByCodeAfterCodeEntityIsRemovedOnPayment(): void
    {
        $customer = new User('payment-code-search@example.test', 'Payment Code Search');
        $payment = new Payment($customer, Money::of(80, 'PLN'));
        new PaymentCode($payment, 'X7K2');
        $this->entityManager->persist($customer);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $payment->setStatus(Payment::STATUS_PAID);
        $this->entityManager->flush();

        self::assertNull($payment->getPaymentCode());
        self::assertSame('X7K2', $payment->getPaymentCodeSnapshot());

        $references = array_values(iterator_to_array(
            self::getContainer()->get(GlobalSearchQuery::class)->search('X7K2'),
        ));

        self::assertTrue($this->contains($references, SearchType::Payment, $payment->getId() ->toRfc4122()));
    }

    public function testLiveComponentRendersDatabaseResultsWithLinksAndHighlighting(): void
    {
        $needle = 'Kalimba' . bin2hex(random_bytes(4));
        $customer = new User(mb_strtolower($needle) . '@example.test', $needle . ' Client');
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $this->browser->loginUser($this->admin);
        $component = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->browser);
        $component->set('query', $needle);
        $html = $component->render()
            ->toString();

        self::assertStringContainsString('Client</span>', $html);
        self::assertStringContainsString('<mark class="bg-amber-200', $html);
        self::assertStringContainsString('/admin/uzytkownicy/' . $customer->getId(), $html);
        self::assertStringContainsString('data-search-result-icon="client"', $html);
        self::assertStringContainsString('bg-sky-100 text-sky-700', $html);
    }

    public function testLiveComponentGeneratesAValidLinkForDatabaseUlid(): void
    {
        $needle = 'Marimba' . bin2hex(random_bytes(4));
        $lesson = $this->lesson($needle . ' Workshops');
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        $this->browser->loginUser($this->admin);
        $component = $this->createLiveComponent(AdminGlobalSearchComponent::class, client: $this->browser);
        $component->set('query', $needle);
        $lessonHtml = $component->render()
            ->toString();

        self::assertStringContainsString('</mark> Workshops', $lessonHtml);
        self::assertStringContainsString('/admin/zajecia/' . $lesson->getId(), $lessonHtml);
        self::assertStringContainsString('data-search-result-icon="lesson"', $lessonHtml);
        self::assertStringContainsString('bg-indigo-100 text-indigo-700', $lessonHtml);
    }

    public function testAdminTourIncludesGlobalSearchStep(): void
    {
        $this->entityManager->flush();
        $this->browser->loginUser($this->admin);
        $this->browser->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-tour-target="global-search"]');
        self::assertSelectorExists('[data-admin-tour-steps-value*="Wyszukiwarka"]');
    }

    public function testSearchesEntityDatesInPolishDayMonthFormat(): void
    {
        $customer = new User('dated-search@example.test', 'Dated Search Client');
        $lesson = $this->lesson('Dated Search Lesson');
        $payment = new Payment($customer, Money::of(80, 'PLN'));
        $booking = new Booking($customer, $payment, $lesson);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($lesson);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $date = '2042-08-15 12:30:00';
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('UPDATE "user" SET created_at = :date WHERE id = :id', [
            'date' => $date,
            'id' => $customer->getId(),
        ]);
        $connection->executeStatement('UPDATE lesson SET schedule = :date WHERE id = :id', [
            'date' => $date,
            'id' => $lesson->getId()
                ->toRfc4122(),
        ]);
        $connection->executeStatement('UPDATE booking SET created_at = :date WHERE id = :id', [
            'date' => $date,
            'id' => $booking->getId()
                ->toRfc4122(),
        ]);
        $connection->executeStatement('UPDATE payment SET created_at = :date WHERE id = :id', [
            'date' => $date,
            'id' => $payment->getId()
                ->toRfc4122(),
        ]);

        $references = array_values(iterator_to_array(
            self::getContainer()->get(GlobalSearchQuery::class)->search('15.08', 30),
        ));
        $types = array_map(static fn(SearchReference $reference): SearchType => $reference->type, $references);

        self::assertContains(SearchType::Client, $types);
        self::assertContains(SearchType::Lesson, $types);
        self::assertContains(SearchType::Booking, $types);
        self::assertContains(SearchType::Payment, $types);

        $results = self::getContainer()->get(SearchResultHydrator::class)->hydrate($references);
        // Matched by id, not just type: $this->admin (persisted in setUp() with a real,
        // un-pinned created_at) is also a Client-type result, and on any test run that
        // happens to execute on the 15th of August, its created_at's real day/month
        // coincidentally matches this test's search string too - array_find picking merely
        // "the first Client result" then non-deterministically grabs the admin instead of
        // $customer, asserting against the wrong row's (real, "today") subtitle date.
        $datedResult = array_find(
            $results,
            static fn($result): bool => $result->reference->type === SearchType::Client
                && $result->reference->id === (string) $customer->getId()
        );
        self::assertNotNull($datedResult);
        self::assertStringContainsString('15.08.2042', $datedResult->subtitle);

        $paymentResult = array_find(
            $results,
            static fn($result): bool => $result->reference->type === SearchType::Payment
        );
        self::assertNotNull($paymentResult);
        self::assertStringContainsString("80\u{00A0}zł", $paymentResult->subtitle);
        self::assertStringNotContainsString('#type', $paymentResult->subtitle);
    }

    /**
     * @param list<SearchReference> $references
     */
    private function contains(array $references, SearchType $type, string $id): bool
    {
        return array_any($references, fn($reference) => $reference->type === $type && $reference->id === $id);
    }

    private function lesson(string $title): Lesson
    {
        return new Lesson(
            new LessonMetadata(
                title: $title,
                lead: 'Opis zajęć',
                visualTheme: '#f97316',
                description: 'Opis funkcjonalnego testu wyszukiwarki',
                capacity: 10,
                duration: 60,
                ageRange: new AgeRange(5, 10),
                category: 'test',
            ),
            new \DateTimeImmutable('+1 day'),
        );
    }
}
