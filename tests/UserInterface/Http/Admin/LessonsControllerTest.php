<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Admin;

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
        $this->assertSelectorTextContains('[data-testid="school-holiday-warning"]', 'Termin podczas przerwy szkolnej');
        $this->assertSelectorTextContains('[data-testid="school-holiday-warning"]', 'Ferie zimowe');
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
        $this->assertSelectorNotExists('[data-testid="school-holiday-warning"]');
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
