<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\File;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Tests\Assembler\LessonAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class LessonModalTermsUrlTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testGetTermsUrlFallsBackToTheStaticFileWhenNoneIsAttached(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $em->flush();

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'closeUrl' => '/warsztaty'],
            client: $client,
        );

        $lessonModal = $component->component();
        static::assertInstanceOf(LessonModal::class, $lessonModal);
        static::assertSame('/docs/Regulamin.pdf', $lessonModal->getTermsUrl());
    }

    public function testGetTermsUrlPointsToTheWorkshopsOwnTermsAttachmentWhenPresent(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $file = new File(
            'regulamin-warsztatow.pdf',
            'application/pdf',
            100,
            str_repeat('a', 64),
            base64_encode('content'),
        );
        $em->persist($file);
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::TERMS_OF_USE, 0);
        $em->flush();

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'closeUrl' => '/warsztaty'],
            client: $client,
        );

        $lessonModal = $component->component();
        static::assertInstanceOf(LessonModal::class, $lessonModal);
        static::assertStringContainsString((string) $file->getId(), $lessonModal->getTermsUrl());
        static::assertStringContainsString('regulamin-warsztatow.pdf', $lessonModal->getTermsUrl());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
