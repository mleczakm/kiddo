<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\SaveAwaitingPayment;
use App\Application\Command\SendReservationNotification;
use App\Entity\AwaitingPaymentFactory;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LessonModal extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?Lesson $lesson = null;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public bool $termsAccepted = false;

    #[LiveProp(writable: true)]
    public ?string $selectedTicketType = null;

    #[LiveProp(writable: true)]
    public int $activeTabIndex = 0;

    #[LiveProp]
    public ?string $paymentStatus = null;

    #[LiveProp]
    public ?string $paymentCode = null;

    #[LiveProp]
    public ?string $paymentAmount = null;

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;

        if ($this->lesson !== null && $this->selectedTicketType === null) {
            $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
            $this->selectedTicketType = $ticketOptions[$this->activeTabIndex]->type->value;
        }
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] int $index, #[LiveArg('tickettype')] string $ticketType): void
    {
        $this->activeTabIndex = $index;
        $this->selectedTicketType = $ticketType;
    }

    #[LiveAction]
    public function processPayment(): void
    {
        if (!$this->termsAccepted) {
            $this->paymentStatus = 'error';
            return;
        }

        if (!$this->selectedTicketType) {
            $this->paymentStatus = 'error';
            return;
        }

        /** @var ?User $user */
        $user = $this->getUser();

        if ($this->lesson && $user) {
            $selected = $this->lesson->getMatchingTicketOption($this->selectedTicketType);
            $awaitingPayment = AwaitingPaymentFactory::create($user, $selected->price);

            $this->bus->dispatch(new SaveAwaitingPayment($user, $awaitingPayment, $this->lesson));

            $this->paymentCode = $awaitingPayment->getCode();
            $this->paymentAmount = (string)$selected->price;
            $this->paymentStatus = 'awaiting_payment';

            return;
        }
        $this->paymentStatus = 'error';
    }

    public function getPaymentCode(): ?string
    {
        return $this->paymentCode;
    }
}
