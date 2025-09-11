<?php

declare(strict_types=1);

namespace App\Tests\Assembler;

use App\Entity\Booking;
use App\Entity\Lesson;
use App\Entity\Payment;
use App\Entity\User;

/**
 * @extends EntityAssembler<Booking>
 */
class BookingAssembler extends EntityAssembler
{
    public function withId(string $id): static
    {
        return $this->with('id', $id);
    }

    public function withUser(User $user): static
    {
        return $this->with('user', $user);
    }

    public function withPayment(?Payment $payment): static
    {
        return $this->with('payment', $payment);
    }

    public function withLessons(Lesson ... $lessons): static
    {
        return $this->with('lessons', $lessons);
    }

    public function withStatus(string $status): static
    {
        return $this->with('status', $status);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('notes', $notes);
    }

    public function withCreatedAt(\DateTimeImmutable $createdAt): static
    {
        return $this->with('createdAt', $createdAt);
    }

    public function withUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        return $this->with('updatedAt', $updatedAt);
    }

    public function assemble(): Booking
    {
        /** @var User $user */
        $user = $this->properties['user'] ?? UserAssembler::new()->assemble();
        /** @var Payment|null $payment */
        $payment = $this->properties['payment'] ?? null;
        /** @var array<Lesson> $lessons */
        $lessons = $this->properties['lessons'] ?? [];

        $booking = new Booking($user, $payment, ...$lessons);

        if (isset($this->properties['id'])) {
            $reflection = new \ReflectionClass($booking);
            $property = $reflection->getProperty('id');
            $property->setValue($booking, $this->properties['id']);
        }

        if (isset($this->properties['status'])) {
            $reflection = new \ReflectionClass($booking);
            $property = $reflection->getProperty('status');
            $property->setValue($booking, $this->properties['status']);
        }

        if (isset($this->properties['notes'])) {
            /** @var string|null $notes */
            $notes = $this->properties['notes'];
            $booking->setNotes($notes);
        }

        if (isset($this->properties['createdAt'])) {
            $reflection = new \ReflectionClass($booking);
            $property = $reflection->getProperty('createdAt');
            $property->setValue($booking, $this->properties['createdAt']);
        }

        if (isset($this->properties['updatedAt'])) {
            $reflection = new \ReflectionClass($booking);
            $property = $reflection->getProperty('updatedAt');
            $property->setValue($booking, $this->properties['updatedAt']);
        }

        return $booking;
    }
}
