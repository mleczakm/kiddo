<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\Transfer;
use App\Repository\SettingRepository;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository,
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

    public function getLastSuccessfulImportDate(): ?\DateTimeImmutable
    {
        $setting = $this->settingRepository->findOneByKey('last_successful_transfer_import');
        if ($setting === null) {
            return null;
        }

        $content = $setting->getContent();
        if (! isset($content['date']) || ! is_string($content['date'])) {
            return null;
        }

        try {
            return new \DateTimeImmutable($content['date']);
        } catch (\Exception) {
            return null;
        }
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
