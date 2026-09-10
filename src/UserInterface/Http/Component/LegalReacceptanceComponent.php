<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\RecordConsents;
use App\Application\Consent\ConsentEvidence;
use App\Application\Consent\ConsentGrant;
use App\Application\Consent\ConsentStatusReader;
use App\Entity\ConsentSource;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Soft panel banner shown when the current Terms or Privacy Policy is published
 * and the user has not accepted it - either because it changed under them, or
 * because they registered before acceptance was enforced. It never blocks the
 * panel: per the Regulamin, not terminating within 10 days already counts as
 * acceptance. The "Akceptuję" button just records that explicitly. Per-booking
 * documents (Regulamin zajęć) are re-accepted at checkout, not here.
 */
#[AsLiveComponent]
final class LegalReacceptanceComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly ConsentStatusReader $statusReader,
        private readonly Security $security,
        private readonly MessageBusInterface $commandBus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @return list<array{label: string, url: string}>
     * @throws \UnexpectedValueException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function getPendingDocuments(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $documents = [];
        foreach ($this->statusReader->accountDocumentsToAccept($user) as $type) {
            $documents[] = [
                'label' => $type->label(),
                'url' => $this->urlGenerator->generate($type->routeName()),
            ];
        }

        return $documents;
    }

    /**
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    #[LiveAction]
    public function reaccept(): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $text = $this->translator->trans('legal.reaccept.acceptance_text');
        $grants = [];
        foreach ($this->statusReader->accountDocumentsToAccept($user) as $type) {
            $grants[] = new ConsentGrant($type->consentType(), ConsentEvidence::currentDocument($type, $text));
        }

        if ($grants !== []) {
            $this->commandBus->dispatch(new RecordConsents($user, ConsentSource::TERMS_REACCEPT, $grants));
        }
    }
}
