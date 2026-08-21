<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Cart\CartItem;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Entity\TicketType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\CartComponent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Stage 11 of the commerce rollout plan: the cart dropdown in the site
 * header. These tests drive the Stage 10 use cases through the component
 * the same way the header's <details> panel does.
 */
#[Group('functional')]
final class CartComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testGetItemsResolvesLessonAndParticipantDisplayInfo(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withTitle('Sensoplastyka')->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->persist(new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        ));
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);

        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        $items = $cartComponent->getItems();

        static::assertCount(1, $items);
        static::assertSame('Sensoplastyka', $items[0]['title']);
        static::assertNull($items[0]['participantName']);
        static::assertSame(1, $cartComponent->getItemCount());
    }

    public function testRemoveDeletesTheItemForItsOwner(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $item = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        );
        $em->persist($cart);
        $em->persist($item);
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);
        $component->call('remove', ['id' => (string) $item->id]);

        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        static::assertSame(0, $cartComponent->getItemCount());
    }

    public function testApplyCodeAppliesAValidPromotionAndUpdatesTotals(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->persist(new PricingRule(
            id: new Ulid(),
            name: 'Header promo',
            adjustmentType: AdjustmentType::FIXED_AMOUNT_OFF,
            adjustmentValue: 1_000,
            promotionCode: 'HEADER10',
        ));
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $item = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        );
        $em->persist($cart);
        $em->persist($item);
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);
        $component->set('promotionCodeInput', 'header10');
        $component->call('applyCode');

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);

        /** @var Cart $reloadedCart */
        $reloadedCart = $freshEm->find(Cart::class, $cart->id);
        /** @var CartItem $reloadedItem */
        $reloadedItem = $freshEm->find(CartItem::class, $item->id);

        static::assertSame('HEADER10', $reloadedCart->promotionCode);
        static::assertSame(4000, $reloadedItem->finalPriceMinor);
    }

    public function testApplyCodeRejectsAnUnknownCode(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $em->persist($user);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($cart);
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);
        $component->set('promotionCodeInput', 'NOPE');
        $component->call('applyCode');

        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        static::assertSame('cart.promotion_code.invalid', $cartComponent->promotionCodeError);
    }

    public function testCheckoutRequiresTermsAcceptance(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $item = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        );
        $em->persist($cart);
        $em->persist($item);
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);
        $component->call('checkout');

        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        static::assertSame('cart.checkout_error_terms', $cartComponent->checkoutError);
        static::assertNull($cartComponent->confirmedOrderNumber);
    }

    public function testCheckoutPlacesAnOrderAndShowsTheCombinedPaymentCode(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $userId = $user->getId();
        static::assertNotNull($userId);

        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $item = new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lesson->getId(),
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        );
        $em->persist($cart);
        $em->persist($item);
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, data: [], client: $client);
        $component->set('termsAccepted', true);
        $component->call('checkout');

        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        static::assertNotNull($cartComponent->confirmedOrderNumber);
        static::assertNotNull($cartComponent->confirmedPaymentCode);
        static::assertSame(5000, $cartComponent->getConfirmedTotal()?->getMinorAmount()->toInt());
        static::assertSame(0, $cartComponent->getItemCount());

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);

        $orders = $freshEm->getRepository(CustomerOrder::class)->findAll();
        static::assertCount(1, $orders);
        static::assertSame(CustomerOrder::SOURCE_CART, $orders[0]->getSource());
    }
}
