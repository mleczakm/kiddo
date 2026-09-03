<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\User;
use App\Infrastructure\Doctrine\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Read-only "accepted terms & documents" list for the panel's Moje dane page -
 * the kiddo take on ActiveNow's "Zaakceptowane zgody i dokumenty". Lists the
 * terms-of-use PDF of every workshop the user has booked (deduplicated), plus
 * the club's general training regulations.
 */
#[AsLiveComponent]
final class PanelDocumentsComponent extends AbstractController
{
    use DefaultActionTrait;

    private const GENERAL_REGULATIONS_PATH = '/docs/Regulamin.pdf';

    public function __construct(
        private readonly BookingRepository $bookingRepository,
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
        $documents = [[
            'title' => 'panel.documents.general_regulations',
            'context' => null,
            'url' => self::GENERAL_REGULATIONS_PATH,
        ]];

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
}
