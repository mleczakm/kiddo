<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Post;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class PostEditor
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.article_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
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

        $post->body->updateEditorial(
            $title,
            $this->nullableTrim($eyebrow),
            $this->nullableTrim($excerpt),
            $this->nullableTrim($linkedWorkshopSlug),
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
