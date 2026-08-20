<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\File;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Tests\Assembler\LessonAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
final class WorkshopFileVisibilityTest extends WebTestCase
{
    public function testAnonymousCanAccessAFileAttachedToAWorkshop(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $file = $this->createFile($em, 'materialy.pdf', 'application/pdf', 'pdf content');
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/materialy.pdf");

        static::assertResponseIsSuccessful();
        static::assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testWorkshopAttachmentIsDownloadedRatherThanShownInline(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $file = $this->createFile($em, 'materialy.pdf', 'application/pdf', 'pdf content');
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::ATTACHMENT, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/materialy.pdf");

        static::assertResponseIsSuccessful();
        static::assertStringContainsString(
            'attachment',
            $client->getResponse()->headers->get('Content-Disposition') ?? '',
        );
    }

    public function testWorkshopTermsOfUseFileIsShownInlineNotDownloaded(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $lesson = LessonAssembler::new()->assemble();
        $em->persist($lesson);
        $file = $this->createFile($em, 'regulamin.pdf', 'application/pdf', 'pdf content');
        new WorkshopFile($lesson->getMetadata(), $file, WorkshopFileRole::TERMS_OF_USE, 0);
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/regulamin.pdf");

        static::assertResponseIsSuccessful();
        $dispositionHeader = $client->getResponse()->headers->get('Content-Disposition') ?? '';
        static::assertStringNotContainsString('attachment', $dispositionHeader);
    }

    public function testAnonymousCannotAccessAFileNotAttachedToAnything(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();

        $file = $this->createFile($em, 'orphan.pdf', 'application/pdf', 'pdf content');
        $em->flush();

        $client->request('GET', "/pliki/{$file->getId()}/orphan.pdf");

        static::assertResponseStatusCodeSame(401);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createFile(EntityManagerInterface $em, string $filename, string $mimeType, string $content): File
    {
        $file = new File($filename, $mimeType, \strlen($content), hash('sha256', $content), base64_encode($content));
        $em->persist($file);
        return $file;
    }
}
