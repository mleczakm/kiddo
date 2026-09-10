<?php

declare(strict_types=1);

namespace App\Application\Consent;

use App\Application\Repository\CartItemRepositoryInterface;
use App\Application\Repository\LessonRepositoryInterface;
use App\Domain\Commerce\Cart\Cart;

final readonly class WorkshopTermsCollector
{
    public function __construct(
        private CartItemRepositoryInterface $cartItemRepository,
        private LessonRepositoryInterface $lessonRepository,
    ) {}

    /** @return list<WorkshopTermsEvidence> */
    public function collect(Cart $cart): array
    {
        $documents = [];
        foreach ($this->cartItemRepository->findByCart($cart->id) as $item) {
            $lesson = $this->lessonRepository->find($item->lessonId);
            if ($lesson === null) {
                continue;
            }
            $terms = $lesson->getMetadata()->getTermsAttachment();
            if ($terms === null) {
                continue;
            }

            $evidence = WorkshopTermsEvidence::fromWorkshopFile($terms, $lesson->getMetadata()->title);
            $documents[$evidence->documentRef] = $evidence;
        }

        return array_values($documents);
    }
}
