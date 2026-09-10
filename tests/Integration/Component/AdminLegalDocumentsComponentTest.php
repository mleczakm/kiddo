<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Application\Command\NotifyLegalDocumentChange;
use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LegalDocumentRepository;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

#[Group('functional')]
final class AdminLegalDocumentsComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    use InteractsWithMessenger;

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
    }

    public function testSettingsUserCanPublishAFileBackedLegalDocumentVersion(): void
    {
        $admin = new User('legal-admin@example.test', 'Legal Admin');
        $admin->setRoles(['ROLE_SETTINGS']);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $filePath = tempnam(sys_get_temp_dir(), 'legal-document-');
        static::assertNotFalse($filePath);
        file_put_contents($filePath, "%PDF-1.4\nlegal document");

        try {
            $component = $this->createLiveComponent(name: 'AdminLegalDocuments', client: $this->client);
            $component->set('documentType', LegalDocumentType::APP_TERMS->value);
            $component->set('effectiveFrom', '2026-09-10');
            $component->set('changeSummary', 'Pierwsza wersja');
            $component->call('publish', files: [
                'legalDocumentFile' => new UploadedFile($filePath, 'regulamin.pdf', 'application/pdf', null, true),
            ]);

            $this->entityManager->clear();
            /** @var LegalDocumentRepository $documentRepository */
            $documentRepository = self::getContainer()->get(LegalDocumentRepository::class);
            /** @var LegalDocumentVersionRepository $versionRepository */
            $versionRepository = self::getContainer()->get(LegalDocumentVersionRepository::class);
            $document = $documentRepository->findOneByType(LegalDocumentType::APP_TERMS);

            static::assertNotNull($document);
            $versions = $versionRepository->findAllForDocument($document);
            static::assertCount(1, $versions);
            static::assertSame(1, $versions[0]->getVersion());
            static::assertSame('Pierwsza wersja', $versions[0]->getChangeSummary());
            static::assertSame('regulamin.pdf', $versions[0]->getFile()->getOriginalName());
            static::assertSame($versions[0]->getFile()->getChecksum(), $versions[0]->getChecksum());
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testPublishingWithTheNotifyOptionQueuesTheChangeNotification(): void
    {
        $admin = new User('legal-notify-admin@example.test', 'Legal Notify Admin');
        $admin->setRoles(['ROLE_SETTINGS']);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $filePath = tempnam(sys_get_temp_dir(), 'legal-document-');
        static::assertNotFalse($filePath);
        file_put_contents($filePath, "%PDF-1.4\nlegal document");

        try {
            $component = $this->createLiveComponent(name: 'AdminLegalDocuments', client: $this->client);
            $component->set('documentType', LegalDocumentType::PRIVACY->value);
            $component->set('effectiveFrom', '2026-09-10');
            $component->set('notifyUsers', true);
            $component->call('publish', files: [
                'legalDocumentFile' => new UploadedFile($filePath, 'polityka.pdf', 'application/pdf', null, true),
            ]);

            $this->transport('async')->queue()->assertContains(NotifyLegalDocumentChange::class, 1);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }

    public function testPublishingWithoutTheNotifyOptionQueuesNothing(): void
    {
        $admin = new User('legal-silent-admin@example.test', 'Legal Silent Admin');
        $admin->setRoles(['ROLE_SETTINGS']);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $filePath = tempnam(sys_get_temp_dir(), 'legal-document-');
        static::assertNotFalse($filePath);
        file_put_contents($filePath, "%PDF-1.4\nlegal document");

        try {
            $component = $this->createLiveComponent(name: 'AdminLegalDocuments', client: $this->client);
            $component->set('documentType', LegalDocumentType::CLASSES_TERMS_GENERAL->value);
            $component->set('effectiveFrom', '2026-09-10');
            $component->call('publish', files: [
                'legalDocumentFile' => new UploadedFile($filePath, 'zajecia.pdf', 'application/pdf', null, true),
            ]);

            $this->transport('async')->queue()->assertNotContains(NotifyLegalDocumentChange::class);
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }
    }
}
