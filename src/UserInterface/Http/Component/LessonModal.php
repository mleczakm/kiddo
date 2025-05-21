<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Lesson;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class LessonModal
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

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;

        if ($this->lesson !== null && $this->selectedTicketType === null) {
            $ticketOptions = iterator_to_array($this->lesson->getTicketOptions());
            $this->selectedTicketType = $ticketOptions[0]->type->value;
        }
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }

    #[LiveAction]
    public function selectTab(int $index, string $ticketType): void
    {
        $this->activeTabIndex = $index;
        $this->selectedTicketType = $ticketType;
    }

    #[LiveAction]
    public function processPayment(): JsonResponse
    {
        if (! $this->termsAccepted) {
            $this->paymentStatus = 'error';
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Musisz zaakceptować warunki i zasady.',
            ]);
        }

        if (! $this->selectedTicketType) {
            $this->paymentStatus = 'error';
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Musisz wybrać typ biletu.',
            ]);
        }

        // Logika płatności z wybranym typem biletu
        $this->paymentStatus = 'success';
        return new JsonResponse([
            'status' => 'success',
            'message' => 'Płatność została pomyślnie przetworzona.',
        ]);
    }
}
