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
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);

        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($host);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($host);
        $client->request('GET', '/admin/zajecia/' . $lesson->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testHostGets404OnALessonTheyreNotAssignedTo(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $host = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $otherInstructor = UserAssembler::new()->withRoles('ROLE_HOST')->assemble();
        $em->persist($host);
        $em->persist($otherInstructor);

        $lesson = LessonAssembler::new()->assemble();
        $lesson->addInstructor($otherInstructor);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($host);
        $client->request('GET', '/admin/zajecia/' . $lesson->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminSeesAnyLessonRegardlessOfInstructor(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $em->persist($admin);

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($admin);
        $client->request('GET', '/admin/zajecia/' . $lesson->getId());

        $this->assertResponseIsSuccessful();
    }
}
