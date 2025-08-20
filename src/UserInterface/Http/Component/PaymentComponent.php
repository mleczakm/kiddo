<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Transfer;
use App\Repository\TransferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class PaymentComponent extends AbstractController
{
    use DefaultActionTrait;

    public function __construct(
        private readonly TransferRepository $transferRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * @return Transfer[]
     */
    public function getTransfers(): array
    {
        return $this->transferRepository->findBy([
            'payment' => null,
        ], [
            'transferredAt' => 'DESC',
        ]);
    }

    #[LiveAction]
    public function reject(#[LiveArg] int $transferId): void
    {
        $transfer = $this->transferRepository->find($transferId);
        if ($transfer) {
            $this->entityManager->remove($transfer);
            $this->entityManager->flush();
        }
    }
}
