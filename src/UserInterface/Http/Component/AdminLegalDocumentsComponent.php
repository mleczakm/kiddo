<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Legal\LegalDocumentPublisher;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\LegalDocumentRepository;
use App\Infrastructure\Doctrine\Repository\LegalDocumentVersionRepository;
use App\UserInterface\Http\Component\Concern\ToastableComponent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('AdminLegalDocuments', template: 'components/AdminLegalDocumentsComponent.html.twig')]
final class AdminLegalDocumentsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ToastableComponent;

    #[LiveProp(writable: true)]
    public string $documentType = LegalDocumentType::APP_TERMS->value;

    #[LiveProp(writable: true)]
    public ?string $effectiveFrom = null;

    #[LiveProp(writable: true)]
    public ?string $changeSummary = null;

    public function __construct(
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly LegalDocumentPublisher $publisher,
    ) {}

    public function mount(): void
    {
        $this->effectiveFrom = Clock::get()->now()->format('Y-m-d');
    }

    /** @return list<LegalDocumentType> */
    public function getDocumentTypes(): array
    {
        return LegalDocumentType::cases();
    }

    /**
     * @return list<array{
     *     type: LegalDocumentType,
     *     document: ?LegalDocument,
     *     current: ?LegalDocumentVersion,
     *     versions: list<LegalDocumentVersion>
     * }>
     * @throws \UnexpectedValueException
     */
    public function getDocumentCards(): array
    {
        $now = Clock::get()->now();
        $cards = [];

        foreach ($this->getDocumentTypes() as $type) {
            $document = $this->documentRepository->findOneByType($type);
            $cards[] = [
                'type' => $type,
                'document' => $document,
                'current' => $this->versionRepository->findCurrent($type, $now),
                'versions' => $document === null ? [] : $this->versionRepository->findAllForDocument($document),
            ];
        }

        return $cards;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     */
    #[LiveAction]
    public function publish(Request $request): void
    {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS');

        /** @var mixed $upload */
        $upload = $request->files->get('legalDocumentFile');
        if (!$upload instanceof UploadedFile) {
            $this->toast('Wybierz plik PDF lub DOCX.', 'error');
            return;
        }

        $type = LegalDocumentType::tryFrom($this->documentType);
        $effectiveFrom = $this->parseDate($this->effectiveFrom);
        $user = $this->getUser();
        if ($type === null || $effectiveFrom === null || !$user instanceof User) {
            $this->toast('Uzupełnij typ dokumentu i poprawną datę obowiązywania.', 'error');
            return;
        }

        try {
            $version = $this->publisher->publish($type, $upload, $effectiveFrom, $user, $this->changeSummary);
        } catch (\InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');
            return;
        }

        $this->changeSummary = null;
        $this->toast(\sprintf('%s: opublikowano wersję %d.', $type->label(), $version->getVersion()));
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
