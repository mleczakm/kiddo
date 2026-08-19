<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

#[Group('unit')]
final class PostTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testConstructorCreatesDraftAndSlugifiesPolishTitle(): void
    {
        $post = new Post('Jak działa światło?', new User('author@example.com', 'Author'));

        static::assertSame('jak-dziala-swiatlo', $post->slug);
        static::assertFalse($post->isPublished());
        static::assertSame(['type' => 'doc', 'content' => []], $post->body->getContentJson());
    }

    public function testScheduledPublicationBecomesVisibleAtRequestedTime(): void
    {
        Clock::set(new MockClock('2026-08-19T10:00:00+02:00'));
        $post = new Post('Article', new User('author@example.com', 'Author'));
        $post->publishAt(new \DateTimeImmutable('2026-08-19T11:00:00+02:00'));

        static::assertFalse($post->isPublished());
        static::assertTrue($post->isPublished(new \DateTimeImmutable('2026-08-19T11:00:00+02:00')));

        $post->unpublish();
        static::assertFalse($post->isPublished(new \DateTimeImmutable('2026-08-20T11:00:00+02:00')));
    }

    public function testPostAllowsOnlyOneCoverAttachment(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        new PostFile($post, $this->file('cover.jpg'), PostFileRole::COVER);

        static::assertNotNull($post->getCoverAttachment());
        $this->expectException(\DomainException::class);

        new PostFile($post, $this->file('other.jpg'), PostFileRole::COVER, 1);
    }

    public function testAttachmentsKeepTheirPositions(): void
    {
        $post = new Post('Article', new User('author@example.com', 'Author'));
        new PostFile($post, $this->file('guide.pdf'), position: 2);
        new PostFile($post, $this->file('consent.docx'), position: 1);

        $positions = array_map(static fn(PostFile $file): int => $file->getPosition(), $post->files->toArray());
        sort($positions);

        static::assertSame([1, 2], $positions);
    }

    private function file(string $name): File
    {
        return new File($name, 'application/octet-stream', 4, hash('sha256', 'data'), base64_encode('data'));
    }
}
