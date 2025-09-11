<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Entity\AgeRange;
use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Payment;
use App\Entity\User;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
class AdminBookingsTabTest extends WebTestCase
{
    private function getEntityManager(): EntityManagerInterface
    {
        $kernel = self::getContainer();
        return $kernel->get('doctrine')
            ->getManager();
    }

    public function testAdminBookingsPageAccessibleForAuthenticatedAdmin(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        // Access the admin bookings page
        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.rounded-lg.border.bg-card', 'Admin bookings component should be present');
        $this->assertSelectorTextContains('h3', 'All Bookings & Payments');
    }

    public function testAdminBookingsPageRedirectsUnauthenticatedUsers(): void
    {
        $client = static::createClient();

        // Try to access admin page without authentication
        $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseRedirects();
    }

    public function testManualBookingFormIsPresent(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if manual booking form elements are present
        $this->assertSelectorExists('input[placeholder="Imię klienta"]', 'Customer name input should be present');
        $this->assertSelectorExists('input[placeholder="Email klienta"]', 'Customer email input should be present');
        $this->assertSelectorExists('input[placeholder="Kwota (zł)"]', 'Amount input should be present');
        $this->assertSelectorExists('select', 'Payment method select should be present');
        $this->assertSelectorExists('textarea[placeholder*="Notatki"]', 'Notes textarea should be present');
        $this->assertSelectorExists(
            'button:contains("Dodaj ręczną rezerwację")',
            'Add booking button should be present'
        );
    }

    public function testBookingsTableIsPresent(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if bookings table is present with correct headers
        $this->assertSelectorExists('table', 'Bookings table should be present');
        $this->assertSelectorTextContains('th', 'Klient');
        $this->assertSelectorTextContains('th', 'Warsztat');
        $this->assertSelectorTextContains('th', 'Kwota');
        $this->assertSelectorTextContains('th', 'Płatność');
        $this->assertSelectorTextContains('th', 'Status rezerwacji');
        $this->assertSelectorTextContains('th', 'Data utworzenia');
        $this->assertSelectorTextContains('th', 'Akcje');
    }

    public function testFilterButtonsArePresent(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if filter buttons are present
        $this->assertSelectorExists('button:contains("Wszystkie")', 'All filter button should be present');
        $this->assertSelectorExists('button:contains("Aktywne")', 'Active filter button should be present');
        $this->assertSelectorExists('button:contains("Zakończone")', 'Completed filter button should be present');
        $this->assertSelectorExists('button:contains("Anulowane")', 'Cancelled filter button should be present');
    }

    public function testSearchInputIsPresent(): void
    {
        $client = static::createClient();

        // Create and authenticate an admin user
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if search input is present
        $this->assertSelectorExists('input[type="text"][placeholder*="Szukaj"]', 'Search input should be present');
    }

    public function testBookingsTableDisplaysExistingBookings(): void
    {
        $client = static::createClient();

        // Create test data
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $testUser = new User('test@example.com', 'Test User');
        $payment = new Payment($testUser, Money::of(10000, 'PLN')); // 100 PLN
        $payment->setStatus(Payment::STATUS_PAID);

        $ageRange = new AgeRange(3, 12);
        $metadata = new LessonMetadata(
            title: 'Test Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Test workshop description',
            capacity: 10,
            schedule: new \DateTimeImmutable('+1 day'),
            duration: 60,
            ageRange: $ageRange,
            category: 'test'
        );

        $lesson = new Lesson($metadata, []);
        $booking = new Booking($testUser, $payment, $lesson);
        $booking->setStatus(Booking::STATUS_CONFIRMED);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->persist($testUser);
        $entityManager->persist($payment);
        $entityManager->persist($lesson);
        $entityManager->persist($booking);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if the test booking is displayed in the table
        $this->assertSelectorTextContains('td', 'Test User');
        $this->assertSelectorTextContains('td', 'test@example.com');
        $this->assertSelectorTextContains('td', 'Test Workshop');
        $this->assertSelectorTextContains('td', '10 000 zł');
    }

    public function testMarkAsPaidButtonIsVisibleForUnpaidBookings(): void
    {
        $client = static::createClient();

        // Create test data with unpaid booking
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();
        $testUser = new User('unpaid@example.com', 'Unpaid User');
        $payment = new Payment($testUser, Money::of(5000, 'PLN')); // 50 PLN
        $payment->setStatus(Payment::STATUS_PENDING);

        $ageRange = new AgeRange(3, 12);
        $metadata = new LessonMetadata(
            title: 'Unpaid Workshop',
            lead: 'Test lead',
            visualTheme: 'default',
            description: 'Unpaid workshop description',
            capacity: 10,
            schedule: new \DateTimeImmutable('+1 day'),
            duration: 60,
            ageRange: $ageRange,
            category: 'test'
        );

        $lesson = new Lesson($metadata, []);
        $booking = new Booking($testUser, $payment, $lesson);
        $booking->setStatus(Booking::STATUS_CONFIRMED);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->persist($testUser);
        $entityManager->persist($payment);
        $entityManager->persist($lesson);
        $entityManager->persist($booking);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if "Mark as Paid" button is visible for unpaid bookings
        $this->assertSelectorExists(
            'button:contains("Oznacz jako opłacone")',
            'Mark as paid button should be visible for unpaid bookings'
        );
    }

    public function testLessonSelectionIsPopulated(): void
    {
        $client = static::createClient();

        // Create test lesson for selection
        $adminUser = UserAssembler::new()
            ->withRoles('ROLE_ADMIN')
            ->assemble();

        $ageRange = new AgeRange(5, 15);
        $metadata = new LessonMetadata(
            title: 'Available Workshop',
            lead: 'Available workshop lead',
            visualTheme: 'default',
            description: 'Available workshop for booking',
            capacity: 15,
            schedule: new \DateTimeImmutable('+2 days'),
            duration: 90,
            ageRange: $ageRange,
            category: 'workshop'
        );

        $lesson = new Lesson($metadata, []);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($adminUser);
        $entityManager->persist($lesson);
        $entityManager->flush();

        $client->loginUser($adminUser);

        $crawler = $client->request('GET', '/admin/rezerwacje');

        $this->assertResponseIsSuccessful();

        // Check if lesson selection shows available lessons
        $this->assertSelectorExists('input[type="checkbox"]', 'Lesson selection checkboxes should be present');
        $this->assertSelectorTextContains('label', 'Available Workshop');
    }
}
