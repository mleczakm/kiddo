<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Cart\CartItem;
use App\Domain\Commerce\Order\BuyerType;
use App\Domain\Commerce\Order\CustomerOrder;
use App\Domain\Commerce\Pricing\AdjustmentType;
use App\Domain\Commerce\Pricing\PricingRule;
use App\Entity\ConsentType;
use App\Entity\File;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentType;
use App\Entity\LegalDocumentVersion;
use App\Entity\TicketType;
use App\Entity\User;
use App\Entity\WorkshopFile;
use App\Entity\WorkshopFileRole;
use App\Infrastructure\Doctrine\Repository\UserConsentRepository;
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

    public function testCheckoutRequiresWithdrawalAcknowledgementAndRecordsDocumentEvidence(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = UserAssembler::new()->assemble();
        $lesson = LessonAssembler::new()->withTitle('Warsztat z regulaminem')->assemble();
        $em->persist($user);
        $em->persist($lesson);
        $em->flush();
        $this->publishCheckoutDocuments($em, $user);

        $termsContents = 'individual workshop terms';
        $termsFile = new File(
            'workshop-terms.pdf',
            'application/pdf',
            \strlen($termsContents),
            hash('sha256', $termsContents),
            base64_encode($termsContents),
        );
        $workshopTerms = new WorkshopFile($lesson->getMetadata(), $termsFile, WorkshopFileRole::TERMS_OF_USE);
        $userId = $user->getId();
        static::assertNotNull($userId);
        $cart = new Cart(id: new Ulid(), customerId: $userId, currency: 'PLN');
        $em->persist($termsFile);
        $em->persist($workshopTerms);
        $em->persist($cart);
        $em->persist($this->cartItem($cart, $lesson->getId()));
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, client: $client);
        $component->set('termsAccepted', true);
        $component->call('checkout');
        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        static::assertSame('cart.checkout_error_withdrawal', $cartComponent->checkoutError);
        static::assertNull($cartComponent->confirmedOrderNumber);

        $component->set('withdrawalAcknowledged', true);
        $component->call('checkout');
        /** @var CartComponent $cartComponent */
        $cartComponent = $component->component();
        $orderNumber = $cartComponent->confirmedOrderNumber;
        static::assertNotNull($orderNumber);

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);
        /** @var CustomerOrder $order */
        $order = $freshEm->getRepository(CustomerOrder::class)->findOneBy(['orderNumber' => $orderNumber]);
        /** @var UserConsentRepository $consentRepository */
        $consentRepository = static::getContainer()->get(UserConsentRepository::class);
        /** @var User $freshUser */
        $freshUser = $freshEm->find(User::class, $userId);
        $consents = $consentRepository->findHistoryForUser($freshUser);

        static::assertCount(4, $consents);
        static::assertEqualsCanonicalizing(
            [
                ConsentType::APP_TERMS->value => 1,
                ConsentType::CLASSES_TERMS->value => 2,
                ConsentType::WITHDRAWAL_INFO_ACK->value => 1,
            ],
            array_count_values(array_map(static fn($consent): string => $consent->getType()->value, $consents)),
        );
        static::assertContains(
            sprintf('workshop_file:%s', $workshopTerms->getId()),
            array_map(static fn($consent): ?string => $consent->getDocumentRef(), $consents),
        );
        foreach ($consents as $consent) {
            static::assertSame(sprintf('order:%s', $order->getId()), $consent->getContext());
        }
    }

    public function testCompanyCheckoutPersistsNormalizedNip(): void
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
        $em->persist($cart);
        $em->persist($this->cartItem($cart, $lesson->getId()));
        $em->flush();

        $client->loginUser($user);
        $component = $this->createLiveComponent(name: CartComponent::class, client: $client);
        $component->set('termsAccepted', true);
        $component->set('buyerType', 'company');
        $component->set('taxIdentifier', '856-734-62-15');
        $component->call('checkout');

        /** @var CustomerOrder $order */
        $order = $em->getRepository(CustomerOrder::class)->findOneBy(['customerId' => $userId]);
        static::assertSame(BuyerType::COMPANY, $order->getBuyerType());
        static::assertSame('8567346215', $order->getTaxIdentifier());
    }

    private function cartItem(Cart $cart, Ulid $lessonId): CartItem
    {
        return new CartItem(
            id: new Ulid(),
            cartId: $cart->id,
            lessonId: $lessonId,
            ticketType: TicketType::ONE_TIME->value,
            participantId: null,
            basePriceMinor: 5000,
            finalPriceMinor: 5000,
            currency: 'PLN',
            pricingQuoteHash: null,
            quotedAt: new \DateTimeImmutable(),
        );
    }

    private function publishCheckoutDocuments(EntityManagerInterface $em, User $publisher): void
    {
        foreach (LegalDocumentType::cases() as $type) {
            $contents = sprintf('%s checkout document', $type->value);
            $file = new File(
                sprintf('%s.pdf', $type->value),
                'application/pdf',
                \strlen($contents),
                hash('sha256', $contents),
                base64_encode($contents),
            );
            $document = new LegalDocument($type);
            new LegalDocumentVersion($document, $file, new \DateTimeImmutable('-1 day'), $publisher);
            $em->persist($file);
            $em->persist($document);
        }

        $em->flush();
    }
}
