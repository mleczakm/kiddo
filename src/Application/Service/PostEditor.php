<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\Post;
use App\Entity\PostFileRole;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class PostEditor
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.article_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
        private FileStorageInterface $fileStorage,
        private PostFileManager $fileManager,
        private InlineAttachmentReconciler $inlineAttachmentReconciler,
    ) {}

    /** @throws \InvalidArgumentException */
    public function updateEditorial(Post $post, string $title, ?string $eyebrow, ?string $excerpt): void
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Article title cannot be empty.');
        }

        $slugger = new AsciiSlugger();
        $previousGeneratedSlug = $slugger->slug($post->body->getTitle())->lower()->toString();
        if ($post->slug === $previousGeneratedSlug) {
            $post->slug = $slugger->slug($title)->lower()->toString();
        }

        $post->body->updateEditorial($title, $this->nullableTrim($eyebrow), $this->nullableTrim($excerpt));
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

    /**
     * Handle file uploads: store each file, attach to post with metadata.
     *
     * @param Post $post
     * @param list<UploadedFile> $uploads
     * @param ?User $uploadedBy
     * @throws \InvalidArgumentException
     * @throws \DomainException
     */
    public function attachFiles(Post $post, array $uploads, ?User $uploadedBy = null): void
    {
        foreach ($uploads as $upload) {
            $policy = new FileUploadPolicy($this->policyUsageFor($upload));
            $file = $this->fileStorage->store($upload, $policy, $uploadedBy);
            $this->fileManager->attachFile($post, $file, PostFileRole::ATTACHMENT, $post->files->count());
        }
    }

    /**
     * Picks which FileUploadPolicy usage bucket an upload belongs to, by
     * sniffing its real content rather than trusting the client-supplied
     * type (which is unreliable even for picking the right allow-list, not
     * just as a security boundary — browsers/clients routinely mislabel or
     * omit it). DatabaseFileStorage sniffs again independently when it
     * actually stores the file; this is only a routing decision.
     */
    private function policyUsageFor(UploadedFile $upload): string
    {
        $mimeType = mime_content_type($upload->getRealPath());
        $mimeType = $mimeType !== false && $mimeType !== '' ? $mimeType : (string) $upload->getClientMimeType();

        return match (true) {
            str_starts_with($mimeType, 'video/') => 'article_video',
            str_starts_with($mimeType, 'image/') => 'article_image',
            default => 'article_document',
        };
    }

    /**
     * Reconcile attachment state after submission.
     *
     * @param Post $post
     * @param list<array{id: string, role: string, position: int, altText: ?string, caption: ?string, downloadName: ?string}> $submitted
     * @throws \InvalidArgumentException
     */
    public function reconcileAttachments(Post $post, array $submitted): void
    {
        $this->fileManager->reconcileAttachments($post, $submitted);
    }

    /**
     * Remove inline PostFile attachments not referenced in the contentJson.
     * Called after content save to clean up abandoned uploads from deleted editor nodes.
     *
     * @param Post $post
     * @param array<string, mixed> $contentJson
     */
    public function reconcileInlineAttachments(Post $post, array $contentJson): void
    {
        $this->inlineAttachmentReconciler->reconcile($post, $contentJson);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
