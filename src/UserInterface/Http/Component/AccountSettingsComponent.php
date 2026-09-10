<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\RequestAccountClosure;
use App\Application\CommandHandler\AnonymizeExpiredAccountsHandler;
use App\Entity\User;
use App\UserInterface\Http\Component\Concern\ToastableComponent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * "Ustawienia konta" panel: download my data, pause the account, or start a
 * permanent deletion. Deletion needs the word "USUŃ" typed to arm the button;
 * the actual scrub runs {@see AnonymizeExpiredAccountsHandler::GRACE_DAYS} days
 * later and logging back in cancels it.
 */
#[AsLiveComponent]
final class AccountSettingsComponent extends AbstractController
{
    use DefaultActionTrait;
    use ToastableComponent;

    public const string DELETE_KEYWORD = 'USUŃ';

    #[LiveProp(writable: true)]
    public string $deleteConfirmation = '';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {}

    public function getGraceDays(): int
    {
        return AnonymizeExpiredAccountsHandler::GRACE_DAYS;
    }

    /** @throws \LogicException */
    public function isDeletionRequested(): bool
    {
        $user = $this->getUser();

        return $user instanceof User && $user->getLifecycle()->deletionRequestedAt() !== null;
    }

    /**
     * @throws \LogicException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    #[LiveAction]
    public function pause(): ?RedirectResponse
    {
        return $this->close(false);
    }

    /**
     * @throws \LogicException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    #[LiveAction]
    public function delete(): ?RedirectResponse
    {
        if (mb_strtoupper(trim($this->deleteConfirmation)) !== self::DELETE_KEYWORD) {
            $this->toast('account.close.confirm_required', 'error');

            return null;
        }

        return $this->close(true);
    }

    /**
     * @throws \LogicException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    private function close(bool $permanent): ?RedirectResponse
    {
        $user = $this->getUser();
        $userId = $user instanceof User ? $user->getId() : null;
        if ($userId === null) {
            return null;
        }

        $this->commandBus->dispatch(new RequestAccountClosure($userId, $permanent));
        $this->addFlash('success', 'account.close.done');

        return $this->redirect($this->generateUrl('app_logout'));
    }
}
