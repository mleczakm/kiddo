<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\AwaitingPaymentFactory;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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
        if (! $this->termsAccepted) {
            $this->paymentStatus = 'error';
            return;
        }

        if (! $this->selectedTicketType) {
            $this->paymentStatus = 'error';
            return;
        }

        /** @var ?User $user */
        $user = $this->getUser();

        if ($this->lesson && $user) {
            $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
            $selected = null;
            foreach ($ticketOptions as $option) {
                if ($option->type->value === $this->selectedTicketType) {
                    $selected = $option;
                    break;
                }
            }
            if ($selected === null) {
                $this->paymentStatus = 'error';
                return;
            }
            $amount = $selected->price;

            $awaitingPayment = AwaitingPaymentFactory::create($user, $amount);
            $this->em->persist($awaitingPayment);
            $this->em->flush();
            $this->paymentCode = $awaitingPayment->getCode();
            $this->paymentAmount = (string) $amount;
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
