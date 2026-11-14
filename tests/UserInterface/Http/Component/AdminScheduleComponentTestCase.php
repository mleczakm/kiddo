<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\AgeRange;
use App\Entity\Lesson;
use App\Entity\LessonMetadata;
use App\Entity\Series;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Covers the merged Warsztaty (Series) + Zajęcia (Lesson) admin view: a
 * Series is grouped with its own occurrences in the selected week, a Lesson
 * without a Series renders as its own row, and a ROLE_HOST-only user only
 * ever sees what they instruct.
 */
abstract class AdminScheduleComponentTestCase extends WebTestCase
{
    use InteractsWithLiveComponents;

    protected EntityManagerInterface $em;

    protected KernelBrowser $client;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        static::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        $admin = UserAssembler::new()->withRoles('ROLE_ADMIN')->assemble();
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }

    protected function createLesson(string $title, \DateTimeImmutable $schedule): Lesson
    {
        $metadata = new LessonMetadata(
            title: $title,
            lead: 'Lead',
            visualTheme: 'default',
            description: 'Desc',
            capacity: 10,
            duration: 60,
            ageRange: new AgeRange(3, 8),
            category: 'test',
        );
        return new Lesson($metadata, $schedule);
    }
}
