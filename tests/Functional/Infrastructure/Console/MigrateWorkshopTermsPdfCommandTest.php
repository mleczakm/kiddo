<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Console;

use App\Entity\File;
use App\Entity\Lesson;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Infrastructure\Console\MigrateWorkshopTermsPdfCommand;
use App\Tests\Assembler\LessonAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
final class MigrateWorkshopTermsPdfCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    private EntityManagerInterface $em;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(MigrateWorkshopTermsPdfCommand::class);
        self::assertInstanceOf(MigrateWorkshopTermsPdfCommand::class, $command);
        $this->commandTester = new CommandTester($command);
        $this->em = $this->getEntityManager();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAttachesTermsPdfToWorkshopsMissingOne(): void
    {
        $lessonA = LessonAssembler::new()->assemble();
        $lessonB = LessonAssembler::new()->assemble();
        $this->em->persist($lessonA);
        $this->em->persist($lessonB);
        $this->em->flush();

        static::assertSame(0, $this->commandTester->execute([]));
        static::assertStringContainsString('2 workshop(s)', $this->commandTester->getDisplay());

        $this->em->clear();
        $reloadedA = $this->em->find(Lesson::class, $lessonA->getId());
        $reloadedB = $this->em->find(Lesson::class, $lessonB->getId());
        static::assertNotNull($reloadedA);
        static::assertNotNull($reloadedB);

        $termsA = $reloadedA->getMetadata()->getTermsAttachment();
        $termsB = $reloadedB->getMetadata()->getTermsAttachment();
        static::assertNotNull($termsA);
        static::assertNotNull($termsB);

        // Both workshops share the exact same stored File row — the PDF was
        // stored once, not once per workshop.
        static::assertTrue($termsA->getFile()->getId()->equals($termsB->getFile()->getId()));
    }

    public function testSkipsWorkshopsThatAlreadyHaveATermsAttachment(): void
    {
        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $existingFile = new File(
            'custom-regulamin.pdf',
            'application/pdf',
            100,
            str_repeat('a', 64),
            base64_encode('x'),
        );
        $this->em->persist($existingFile);
        new WorkshopFile($lesson->getMetadata(), $existingFile, WorkshopFileRole::TERMS_OF_USE, 0);
        $this->em->flush();
        $lessonId = $lesson->getId();

        static::assertSame(0, $this->commandTester->execute([]));

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        $terms = $reloaded->getMetadata()->getTermsAttachment();
        static::assertNotNull($terms);
        static::assertSame('custom-regulamin.pdf', $terms->getFile()->getOriginalName());
    }

    public function testDryRunAttachesNothing(): void
    {
        $lesson = LessonAssembler::new()->assemble();
        $this->em->persist($lesson);
        $this->em->flush();
        $lessonId = $lesson->getId();

        static::assertSame(0, $this->commandTester->execute([
            '--dry-run' => true,
        ]));
        static::assertStringContainsString('Dry run', $this->commandTester->getDisplay());

        $this->em->clear();
        $reloaded = $this->em->find(Lesson::class, $lessonId);
        static::assertNotNull($reloaded);
        static::assertNull($reloaded->getMetadata()->getTermsAttachment());
    }

    public function testRunningTwiceReusesTheSameStoredFileInstead(): void
    {
        $lessonA = LessonAssembler::new()->assemble();
        $this->em->persist($lessonA);
        $this->em->flush();

        static::assertSame(0, $this->commandTester->execute([]));

        $this->em->clear();
        $reloadedA = $this->em->find(Lesson::class, $lessonA->getId());
        static::assertNotNull($reloadedA);
        $firstFileId = $reloadedA->getMetadata()->getTermsAttachment()?->getFile()->getId();
        static::assertNotNull($firstFileId);

        $lessonB = LessonAssembler::new()->assemble();
        $this->em->persist($lessonB);
        $this->em->flush();

        static::assertSame(0, $this->commandTester->execute([]));

        $this->em->clear();
        $reloadedB = $this->em->find(Lesson::class, $lessonB->getId());
        static::assertNotNull($reloadedB);
        $secondFileId = $reloadedB->getMetadata()->getTermsAttachment()?->getFile()->getId();
        static::assertNotNull($secondFileId);

        static::assertTrue($firstFileId->equals($secondFileId));
    }
}
