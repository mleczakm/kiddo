<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http;

use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;

#[Group('functional')]
final class LegalDocumentVersionActionTest extends WebTestCase
{
    public function testCurrentVersionAndArchiveArePubliclyAvailable(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        [$first, $second] = $this->createTwoPublishedVersions($em);

        $crawler = $client->request('GET', '/regulamin');

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('main', 'wersja 2');
        static::assertCount(1, $crawler->filter(\sprintf('iframe[src*="%s"]', $second->getFile()->getId())));
        static::assertSelectorTextContains('main', 'Poprzednie wersje');

        $crawler = $client->request('GET', '/regulamin?v=' . (string) $first->getId());

        static::assertResponseIsSuccessful();
        static::assertSelectorTextContains('main', 'Przeglądasz archiwalną wersję dokumentu.');
        static::assertCount(1, $crawler->filter(\sprintf('iframe[src*="%s"]', $first->getFile()->getId())));
    }

    public function testPublishedLegalPdfIsServedInline(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        [, $version] = $this->createTwoPublishedVersions($em);
        $file = $version->getFile();

        $client->request('GET', \sprintf('/pliki/%s/%s', $file->getId(), $file->getOriginalName()));

        static::assertResponseIsSuccessful();
        static::assertStringNotContainsString(
            'attachment',
            $client->getResponse()->headers->get('Content-Disposition') ?? '',
        );
    }

    public function testFutureVersionCannotBeReadFromPublicRouteOrFileEndpoint(): void
    {
        $client = static::createClient();
        $em = $this->getEntityManager();
        $publisher = new User('future-publisher@example.test', 'Publisher');
        $document = new LegalDocument(LegalDocumentType::PRIVACY);
        $file = $this->file('future.pdf', 'future');
        $version = new LegalDocumentVersion($document, $file, Clock::get()->now()->modify('+1 day'), $publisher);
        $em->persist($publisher);
        $em->persist($document);
        $em->persist($file);
        $em->persist($version);
        $em->flush();

        $client->request('GET', '/polityka-prywatnosci?v=' . (string) $version->getId());
        static::assertResponseStatusCodeSame(404);

        $client->request('GET', \sprintf('/pliki/%s/%s', $file->getId(), $file->getOriginalName()));
        static::assertResponseStatusCodeSame(401);
    }

    /** @return array{LegalDocumentVersion, LegalDocumentVersion} */
    private function createTwoPublishedVersions(EntityManagerInterface $em): array
    {
        $publisher = new User('legal-publisher@example.test', 'Publisher');
        $document = new LegalDocument(LegalDocumentType::APP_TERMS);
        $firstFile = $this->file('regulamin-v1.pdf', 'first');
        $secondFile = $this->file('regulamin-v2.pdf', 'second');
        $first = new LegalDocumentVersion(
            $document,
            $firstFile,
            Clock::get()->now()->modify('-2 days'),
            $publisher,
            'Pierwsza wersja',
        );
        $second = new LegalDocumentVersion(
            $document,
            $secondFile,
            Clock::get()->now()->modify('-1 day'),
            $publisher,
            'Aktualizacja',
        );

        $em->persist($publisher);
        $em->persist($document);
        $em->persist($firstFile);
        $em->persist($secondFile);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        return [$first, $second];
    }

    private function file(string $name, string $contents): File
    {
        return new File(
            $name,
            'application/pdf',
            \strlen($contents),
            hash('sha256', $contents),
            base64_encode($contents),
        );
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
