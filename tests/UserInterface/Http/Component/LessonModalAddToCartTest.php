<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Domain\Commerce\Cart\Cart;
use App\Domain\Commerce\Cart\CartItem;
use App\Entity\Child;
use App\Entity\TicketType;
use App\Entity\WorkshopType;
use App\Tests\Assembler\LessonAssembler;
use App\Tests\Assembler\LessonMetadataAssembler;
use App\Tests\Assembler\SeriesAssembler;
use App\Tests\Assembler\UserAssembler;
use App\UserInterface\Http\Component\LessonModal;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Stage 11 of the commerce rollout plan: LessonModal::addToCart() wires the
 * placeholder "add to cart" button to the Stage 10 cart backend, without
 * touching processPayment()'s existing immediate-checkout behavior (see
 * LessonModalPaymentTest for that).
 */
#[Group('functional')]
final class LessonModalAddToCartTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testAddsAnItemToTheCustomersCartAndMarksItAdded(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withPhone('501111111')->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()
            ->withMetadata(LessonMetadataAssembler::new()->withTitle('Sensory')->assemble())
            ->withSchedule(new \DateTimeImmutable('2024-02-21 10:30:00'))
            ->assemble();
        $lesson->setSeries($series);
        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: [
                'lesson' => $lesson,
                'modalOpened' => true,
            ],
            client: $client,
        );

        $component->call('addToCart');

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        static::assertTrue($lessonModal->addedToCart);
        static::assertNull($lessonModal->addToCartError);

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);

        $carts = $freshEm->getRepository(Cart::class)->findAll();
        static::assertCount(1, $carts);
        static::assertSame($user->getId(), $carts[0]->customerId);

        $items = $freshEm->getRepository(CartItem::class)->findBy(['cartId' => $carts[0]->id]);
        static::assertCount(1, $items);
        static::assertTrue($items[0]->lessonId->equals($lesson->getId()));
        static::assertSame(TicketType::ONE_TIME->value, $items[0]->ticketType);
    }

    public function testAddingTheSameSelectionTwiceShowsADuplicateErrorInstead(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withPhone('501111111')->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $lesson->setSeries($series);
        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'modalOpened' => true],
            client: $client,
        );

        $component->call('addToCart');
        $component->call('addToCart');

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        static::assertFalse($lessonModal->addedToCart);
        static::assertSame('cart.add_error_duplicate', $lessonModal->addToCartError);
    }

    public function testAddingWithASelectedChildStoresTheParticipant(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = UserAssembler::new()->withPhone('501111111')->assemble();
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $lesson->setSeries($series);
        $child = new Child($user, 'Zosia', new \DateTimeImmutable('2020-01-01'));
        $em->persist($user);
        $em->persist($series);
        $em->persist($lesson);
        $em->persist($child);
        $em->flush();

        $client->loginUser($user);

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'modalOpened' => true, 'selectedChildId' => (string) $child->getId()],
            client: $client,
        );

        $component->call('addToCart');

        /** @var \Doctrine\Persistence\ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $registry->resetManager();
        /** @var EntityManagerInterface $freshEm */
        $freshEm = static::getContainer()->get(EntityManagerInterface::class);

        $items = $freshEm->getRepository(CartItem::class)->findAll();
        static::assertCount(1, $items);
        static::assertTrue($items[0]->participantId?->equals($child->getId()));
    }

    public function testDoesNothingForAnAnonymousUser(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $series = SeriesAssembler::new()->withType(WorkshopType::ONE_TIME)->assemble();
        $lesson = LessonAssembler::new()->assemble();
        $lesson->setSeries($series);
        $em->persist($series);
        $em->persist($lesson);
        $em->flush();

        $component = $this->createLiveComponent(
            name: LessonModal::class,
            data: ['lesson' => $lesson, 'modalOpened' => true],
            client: $client,
        );

        $component->call('addToCart');

        /** @var LessonModal $lessonModal */
        $lessonModal = $component->component();
        static::assertFalse($lessonModal->addedToCart);
        static::assertSame('cart.add_error_generic', $lessonModal->addToCartError);
        static::assertCount(0, $em->getRepository(Cart::class)->findAll());
    }
}
