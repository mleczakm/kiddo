<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Entity\DTO\CancelledLesson;
use App\Entity\DTO\RescheduledLesson;
use App\Infrastructure\Doctrine\LessonMapType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

#[Group('unit')]
final class LessonMapTypeTest extends TestCase
{
    private const string ORIGINAL_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const string NEW_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    private LessonMapType $type;

    private PostgreSQLPlatform $platform;

    #[\Override]
    protected function setUp(): void
    {
        $this->type = new LessonMapType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testItPreservesCancelledAndRescheduledMetadataAcrossARoundTrip(): void
    {
        $payload = json_encode([
            'lessons' => [
                self::ORIGINAL_ID => [
                    'lessonId' => self::ORIGINAL_ID,
                ],
                self::NEW_ID => [
                    'lessonId' => self::NEW_ID,
                ],
            ],
            'active' => [],
            'past' => [],
            'cancelled' => [
                self::ORIGINAL_ID => [
                    'lessonId' => self::NEW_ID,
                    'rescheduledFrom' => self::ORIGINAL_ID,
                    'rescheduledBy' => 42,
                    'rescheduledAt' => '2026-08-18T12:00:00+00:00',
                ],
                self::NEW_ID => [
                    'lessonId' => self::NEW_ID,
                    'cancelledBy' => 7,
                    'cancelledAt' => '2026-08-19T12:00:00+00:00',
                    'reason' => 'illness',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $map = $this->type->convertToPHPValue($payload, $this->platform);

        static::assertNotNull($map);
        $rescheduled = $map->cancelled->get(Ulid::fromString(self::ORIGINAL_ID));
        static::assertInstanceOf(RescheduledLesson::class, $rescheduled);
        static::assertSame(self::NEW_ID, $rescheduled->lessonId->toString());
        static::assertSame(42, $rescheduled->rescheduledBy);

        $cancelled = $map->cancelled->get(Ulid::fromString(self::NEW_ID));
        static::assertInstanceOf(CancelledLesson::class, $cancelled);
        static::assertSame(7, $cancelled->cancelledBy);
        static::assertSame('illness', $cancelled->reason);

        $serialized = $this->type->convertToDatabaseValue($map, $this->platform);
        $restored = $this->type->convertToPHPValue($serialized, $this->platform);
        static::assertNotNull($restored);
        static::assertInstanceOf(
            RescheduledLesson::class,
            $restored->cancelled->get(Ulid::fromString(self::ORIGINAL_ID)),
        );
    }

    public function testItRejectsMalformedLessonEntries(): void
    {
        $payload = json_encode([
            'lessons' => [
                self::ORIGINAL_ID => 123,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Lesson map entries must be strings or objects.');

        $this->type->convertToPHPValue($payload, $this->platform);
    }
}
