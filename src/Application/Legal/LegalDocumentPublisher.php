<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Application\Command\NotifyLegalDocumentChange;
use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Infrastructure\Doctrine\Repository\LegalDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class LegalDocumentPublisher
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileStorageInterface $fileStorage,
        private LegalDocumentRepository $documentRepository,
        private MessageBusInterface $commandBus,
    ) {}

    /**
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function publish(NewLegalDocumentVersion $request, bool $notifyUsers = false): LegalDocumentVersion
    {
        $changeSummary = $this->normalizeChangeSummary($request->changeSummary);
        $file = $this->fileStorage->store(
            $request->upload,
            new FileUploadPolicy('legal_document'),
            $request->publishedBy,
        );

        $document = $this->documentRepository->findOneByType($request->type);
        if ($document === null) {
            $document = new LegalDocument($request->type);
            $this->entityManager->persist($document);
        }

        $version = new LegalDocumentVersion(
            $document,
            $file,
            $request->effectiveFrom,
            $request->publishedBy,
            $changeSummary,
        );
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        if ($notifyUsers) {
            $this->commandBus->dispatch(new NotifyLegalDocumentChange($version->getId()));
        }

        return $version;
    }

    /** @throws \InvalidArgumentException */
    private function normalizeChangeSummary(?string $changeSummary): ?string
    {
        $changeSummary = $changeSummary === null ? null : trim($changeSummary);
        if ($changeSummary === '') {
            return null;
        }
        if ($changeSummary !== null && mb_strlen($changeSummary) > 1000) {
            throw new \InvalidArgumentException('A legal document change summary cannot exceed 1000 characters.');
        }

        return $changeSummary;
    }
}
