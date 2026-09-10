<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\LegalDocumentType;
use App\Entity\User;
use App\Entity\UserConsent;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Read-only "accepted terms & consents" panel for the Moje dane page - the kiddo
 * take on ActiveNow's "Zaakceptowane zgody i dokumenty". Lists the current legal
 * documents plus the terms-of-use file of every workshop the user has booked,
 * and - once the consent register carries rows - the user's own acceptance
 * history (what, which version, when, from where, still valid or withdrawn).
 * Marketing consent is withdrawn from the profile newsletter toggle, not here.
 */
#[AsLiveComponent]
final class PanelDocumentsComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly UserConsentRepository $consentRepository,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return list<array{title: string, context: ?string, url: string}>
     *
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function getDocuments(): array
    {
        $documents = [
            [
                'title' => 'panel.documents.app_terms',
                'context' => null,
                'url' => $this->urlGenerator->generate('legal_app_terms'),
            ],
            [
                'title' => 'panel.documents.classes_terms',
                'context' => null,
                'url' => $this->urlGenerator->generate('legal_classes_terms'),
            ],
            [
                'title' => 'panel.documents.privacy',
                'context' => null,
                'url' => $this->urlGenerator->generate('legal_privacy'),
            ],
        ];

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $documents;
        }

        $seen = [];
        foreach ($this->bookingRepository->findVisibleForUser($user) as $booking) {
            foreach ($booking->getLessons() as $lesson) {
                $terms = $lesson->getMetadata()->getTermsAttachment();
                if ($terms === null) {
                    continue;
                }

                $fileId = (string) $terms->getFile()->getId();
                if (in_array($fileId, $seen, true)) {
                    continue;
                }
                $seen[] = $fileId;

                $documents[] = [
                    'title' => $terms->getFile()->getOriginalName(),
                    'context' => $lesson->getMetadata()->title,
                    'url' => $this->urlGenerator->generate('stored_file', [
                        'id' => $fileId,
                        'safeName' => $terms->getFile()->getOriginalName(),
                    ]),
                ];
            }
        }

        return $documents;
    }

    /**
     * @return list<array{
     *     type: string,
     *     reference: ?string,
     *     grantedAt: \DateTimeImmutable,
     *     revokedAt: ?\DateTimeImmutable,
     *     source: string,
     *     active: bool,
     * }>
     * @throws \UnexpectedValueException
     */
    public function getConsentHistory(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_map(fn(UserConsent $consent): array => [
            'type' => 'panel.consents.type.' . $consent->getType()->value,
            'reference' => $this->consentReference($consent),
            'grantedAt' => $consent->getGrantedAt(),
            'revokedAt' => $consent->getRevokedAt(),
            'source' => 'panel.consents.source.' . $consent->getSource()->value,
            'active' => $consent->isActive(),
        ], $this->consentRepository->findHistoryForUser($user));
    }

    private function consentReference(UserConsent $consent): ?string
    {
        $version = $consent->getDocumentVersion();
        if ($version !== null) {
            return sprintf('%s v%d', $consent->getDocumentType()?->label() ?? '', $version->getVersion());
        }

        $documentRef = $consent->getDocumentRef();
        if ($documentRef !== null && str_starts_with($documentRef, 'workshop_file:')) {
            return LegalDocumentType::CLASSES_TERMS_GENERAL->label();
        }

        return null;
    }
}
