<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Post;
use App\Repository\LessonMetadataRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class PostEditor
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.article_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
        private LessonMetadataRepository $lessonMetadataRepository,
    ) {}

    /** @throws \InvalidArgumentException */
    public function updateEditorial(
        Post $post,
        string $title,
        ?string $eyebrow,
        ?string $excerpt,
        ?string $linkedWorkshopSlug,
    ): void {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Article title cannot be empty.');
        }

        $slugger = new AsciiSlugger();
        $previousGeneratedSlug = $slugger->slug($post->body->getTitle())->lower()->toString();
        if ($post->slug === $previousGeneratedSlug) {
            $post->slug = $slugger->slug($title)->lower()->toString();
        }

        $linkedWorkshopSlug = $this->nullableTrim($linkedWorkshopSlug);
        if ($linkedWorkshopSlug !== null && !$this->lessonMetadataRepository->slugExists($linkedWorkshopSlug)) {
            throw new \InvalidArgumentException('Linked workshop slug does not match any workshop.');
        }

        $post->body->updateEditorial(
            $title,
            $this->nullableTrim($eyebrow),
            $this->nullableTrim($excerpt),
            $linkedWorkshopSlug,
        );
        $post->markUpdated();
    }

    /**
     * @param array<string, mixed> $contentJson
     * @throws \InvalidArgumentException
     */
    public function updateContent(Post $post, array $contentJson, string $unsafeHtml): void
    {
        if (($contentJson['type'] ?? null) !== 'doc') {
            throw new \InvalidArgumentException('Article content must be a Tiptap document.');
        }

        $post->body->updateContent($contentJson, $this->sanitizer->sanitize($unsafeHtml));
        $post->markUpdated();
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
