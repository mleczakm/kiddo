<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes inline PostFile attachments no longer referenced in an article's
 * contentJson. Called after content save to clean up abandoned uploads from
 * deleted editor nodes.
 */
final readonly class InlineAttachmentReconciler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /** @param array<string, mixed> $contentJson */
    public function reconcile(Post $post, array $contentJson): void
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
     * Extract all file IDs referenced in image-carrying nodes within
     * contentJson. Pattern: nodes with type='image' (plain inline image) or
     * 'articleFigure' (image + caption, assets/tiptap/article-figure.js) and
     * attrs.src containing a stored_file URL.
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
            if (!\in_array($node['type'] ?? null, ['image', 'articleFigure'], true)) {
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
}
