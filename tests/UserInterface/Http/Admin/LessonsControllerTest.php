<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

use App\Tests\Assembler\BookingAssembler;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('functional')]
final class LessonsControllerTest extends WebTestCase
{
    public function testHostSeesALessonTheyreAssignedTo(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);

        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($host);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($host);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('button[data-live-action-param="openModal"]', 'Modyfikuj');
        $this->assertSelectorTextNotContains('body', 'Zmień termin');
    }

    public function testHostGets404OnALessonTheyreNotAssignedTo(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $otherInstructor = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);
        $em->persist($otherInstructor);

        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($otherInstructor);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($host);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminSeesAnyLessonRegardlessOfInstructor(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testAdminSeesWarningForLessonDuringMasovianSchoolHolidays(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->withSchedule(
            new \DateTimeImmutable('2027-02-01 10:00:00', new \DateTimeZone('Europe/Warsaw')),
        )->assemble();
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '[data-testid="holiday-warning"]',
            'Termin podczas święta lub przerwy szkolnej',
        );
        $this->assertSelectorTextContains('[data-testid="holiday-warning"]', 'Ferie zimowe');
    }

    public function testAdminSeesWarningForLessonDuringPolishPublicHoliday(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->withSchedule(
            new \DateTimeImmutable('2026-11-11 10:00:00', new \DateTimeZone('Europe/Warsaw')),
        )->assemble();
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-testid="holiday-warning"]', 'Narodowe Święto Niepodległości');
    }

    public function testAdminDoesNotSeeWarningForLessonOutsideSchoolHolidays(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->withSchedule(
            new \DateTimeImmutable('2027-03-01 10:00:00', new \DateTimeZone('Europe/Warsaw')),
        )->assemble();
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-testid="holiday-warning"]');
    }

    public function testCancelledBookingsAreGroupedBehindTheInactiveReservationsDisclosure(): void
    {
        $client = static::createClient();
        $em = $this->entityManager();

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();

        $activeUser = UserAssembler::new()->assemble();
        $active = BookingAssembler::new()
            ->withStatus('active')
            ->withUser($activeUser)
            ->withLessons($lesson)
            ->assemble();

        $em->persist($activeUser);
        $em->persist($active);
        $lesson->addBooking($active);

        foreach (['Anna Anulowana', 'Bartek Anulowany'] as $name) {
            $user = UserAssembler::new()->withName($name)->assemble();
            $cancelled = BookingAssembler::new()
                ->withStatus('cancelled')
                ->withUser($user)
                ->withLessons($lesson)
                ->assemble();
            $em->persist($user);
            $em->persist($cancelled);
            $lesson->addBooking($cancelled);
        }

        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . (string) $lesson->getId());

        $this->assertResponseIsSuccessful();
        // Two cancelled bookings collapse behind the shared disclosure...
        $this->assertSelectorTextContains(
            '[data-controller="disclosure"] [data-disclosure-trigger]',
            'nieaktywne rezerwacje',
        );
        // ...and their rows are struck through and hidden until expanded.
        $this->assertSelectorExists('tr[data-disclosure-target="content"][hidden] .line-through');
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
