<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Entity\File;
use App\Entity\Post;
use App\Entity\PostFile;
use App\Entity\PostFileRole;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Manages file attachments for posts: reconciliation, reordering, role assignment.
 */
final readonly class PostFileManager
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * Reconcile submitted file state against the post's current attachments.
     * Handles additions, removals, role changes, and reordering.
     *
     * @param Post $post
     * @param list<array{id: string, role: string, position: int, altText: ?string, caption: ?string, downloadName: ?string}> $submitted
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \ValueError
     */
    public function reconcileAttachments(Post $post, array $submitted): void
    {
        // Keyed by PostFile's own id, matching what the edit form actually
        // renders into files_id[]/files_role[...] (the join row identity,
        // not the underlying File's id — the two only happen to coincide
        // for a post's first attachment of each file, which masked this
        // mismatch until a role change on an existing file surfaced it).
        $existingMap = [];
        foreach ($post->files as $existing) {
            $existingMap[(string) $existing->getId()] = $existing;
        }

        $submittedIds = [];
        foreach ($submitted as $item) {
            $postFileId = $item['id'];
            $submittedIds[] = $postFileId;

            if (!isset($existingMap[$postFileId])) {
                throw new InvalidArgumentException("PostFile {$postFileId} is not attached to this post.");
            }

            $postFile = $existingMap[$postFileId];
            $postFile->setRole(PostFileRole::from($item['role']));
            $postFile->setPosition($item['position']);
            $postFile->setAltText($item['altText'] ?? null);
            $postFile->setCaption($item['caption'] ?? null);
            $postFile->setDownloadName($item['downloadName'] ?? null);
        }

        foreach ($existingMap as $postFileId => $postFile) {
            if (\in_array($postFileId, $submittedIds, true)) {
                continue;
            }

            $post->removeFile($postFile);
        }

        $this->em->flush();
    }

    /**
     * Attach a file to a post with the given role. Callers needing alt
     * text/caption/download name can set them on the returned PostFile
     * before the next flush.
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function attachFile(Post $post, File $file, PostFileRole $role, int $position = 0): PostFile
    {
        $postFile = new PostFile($post, $file, $role, $position);

        $this->em->persist($postFile);
        $this->em->flush();

        return $postFile;
    }

    /**
     * Remove an attachment from a post without deleting the underlying file.
     *
     * @throws InvalidArgumentException
     */
    public function detachFile(Post $post, PostFile $postFile): void
    {
        if ($postFile->getPost() !== $post) {
            throw new InvalidArgumentException('PostFile does not belong to the given post.');
        }

        $post->removeFile($postFile);
        $this->em->flush();
    }
}
