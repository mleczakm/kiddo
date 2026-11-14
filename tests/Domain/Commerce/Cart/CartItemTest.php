<?php

declare(strict_types=1);

namespace App\Tests\Domain\Commerce\Cart;

use App\Domain\Commerce\Cart\CartItem;
use App\Domain\Commerce\Cart\CartItemDetails;
use App\Domain\Commerce\Cart\CartItemPrice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class CartItemTest extends TestCase
{
    public function testMatchesSelectionIsTrueForTheSameLessonTicketTypeAndParticipant(): void
    {
        $lessonId = new Ulid();
        $participantId = new Ulid();
        $item = $this->makeItem($lessonId, 'one_time', $participantId);

        static::assertTrue($item->matchesSelection($lessonId, 'one_time', $participantId));
    }

    public function testMatchesSelectionIsFalseForADifferentLesson(): void
    {
        $item = $this->makeItem(new Ulid(), 'one_time', null);

        static::assertFalse($item->matchesSelection(new Ulid(), 'one_time', null));
    }

    public function testMatchesSelectionIsFalseForADifferentTicketType(): void
    {
        $lessonId = new Ulid();
        $item = $this->makeItem($lessonId, 'one_time', null);

        static::assertFalse($item->matchesSelection($lessonId, 'carnet_4', null));
    }

    public function testMatchesSelectionDistinguishesNullFromASpecificParticipant(): void
    {
        $lessonId = new Ulid();
        $item = $this->makeItem($lessonId, 'one_time', null);

        static::assertFalse($item->matchesSelection($lessonId, 'one_time', new Ulid()));
    }

    public function testMatchesSelectionIsFalseForADifferentParticipant(): void
    {
        $lessonId = new Ulid();
        $item = $this->makeItem($lessonId, 'one_time', new Ulid());

        static::assertFalse($item->matchesSelection($lessonId, 'one_time', new Ulid()));
    }

    private function makeItem(Ulid $lessonId, string $ticketType, ?Ulid $participantId): CartItem
    {
        return new CartItem(
            id: new Ulid(),
            cartId: new Ulid(),
            lessonId: $lessonId,
            ticketType: $ticketType,
            details: new CartItemDetails(
                participantId: $participantId,
                currency: 'PLN',
                price: new CartItemPrice(baseMinor: 5000, finalMinor: 5000, quoteHash: null, quotedAt: null),
            ),
        );
    }
}
