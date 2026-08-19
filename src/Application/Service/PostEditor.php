<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\File\FileStorageInterface;
use App\Application\File\FileUploadPolicy;
use App\Entity\Post;
use App\Entity\PostFileRole;
use App\Entity\User;
use App\Repository\LessonMetadataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class PostEditor
{
    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.app.article_sanitizer')]
        private HtmlSanitizerInterface $sanitizer,
        private LessonMetadataRepository $lessonMetadataRepository,
        private FileStorageInterface $fileStorage,
        private PostFileManager $fileManager,
        private EntityManagerInterface $em,
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
        $policy = new FileUploadPolicy('article_image');

        $totalSize = 0;
        foreach ($post->files as $existing) {
            $totalSize += $existing->getFile()->getSize();
        }

        foreach ($uploads as $upload) {
            $file = $this->fileStorage->store($upload, $policy, $uploadedBy);
            $this->fileManager->attachFile(
                $post,
                $file,
                PostFileRole::ATTACHMENT,
                $post->files->count(),
            );

            $totalSize += $file->getSize();
        }
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
        $inlineFileIds = $this->extractInlineFileIds($contentJson);

        foreach ($post->files as $postFile) {
            if ($postFile->getRole()->value !== 'inline') {
                continue;
            }

            if (!\in_array((string) $postFile->getFile()->getId(), $inlineFileIds, true)) {
                $post->removeFile($postFile);
            }
        }

        $this->em->flush();
    }

    /**
     * Extract all file IDs referenced in image nodes within contentJson.
     * Pattern: nodes with type='image' and attrs.src containing stored_file URLs.
     *
     * @param array<string, mixed> $contentJson
     * @return list<string>
     */
    private function extractInlineFileIds(array $contentJson): array
    {
        $fileIds = [];

        if (!isset($contentJson['content']) || !\is_array($contentJson['content'])) {
            return $fileIds;
        }

        $this->walkContentNodes($contentJson['content'], static function (array $node) use (&$fileIds): void {
            if (($node['type'] ?? null) !== 'image') {
                return;
            }

            $src = $node['attrs']['src'] ?? null;
            if ($src && \is_string($src) && preg_match('~/pliki/([A-Za-z0-9]{26})/~', $src, $matches)) {
                $fileIds[] = $matches[1];
            }
        });

        return $fileIds;
    }

    /**
     * Recursively walk content nodes to find images.
     *
     * @param list<array<string, mixed>> $nodes
     * @param callable(array<string, mixed>): void $callback
     */
    private function walkContentNodes(array $nodes, callable $callback): void
    {
        foreach ($nodes as $node) {
            $callback($node);

            if (isset($node['content']) && \is_array($node['content'])) {
                $this->walkContentNodes($node['content'], $callback);
            }
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
