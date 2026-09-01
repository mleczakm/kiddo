<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\UseCase\RejectTransfer;
use App\Entity\Transfer;
use App\Infrastructure\Doctrine\Repository\SettingRepository;
use App\Infrastructure\Doctrine\Repository\TransferRepository;
use Psr\Log\LoggerInterface;
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
        private readonly SettingRepository $settingRepository,
        private readonly RejectTransfer $rejectTransfer,
        private readonly LoggerInterface $logger,
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
        if (!isset($content['date']) || !is_string($content['date'])) {
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
        try {
            ($this->rejectTransfer)($transferId);
        } catch (\RuntimeException $exception) {
            // The transfer was assigned to a payment between this list render
            // and the click - the refreshed list no longer offers it to reject.
            $this->logger->warning('Transfer rejection was refused', [
                'transferId' => $transferId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
