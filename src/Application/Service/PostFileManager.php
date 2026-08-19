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
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Reconcile submitted file state against the post's current attachments.
     * Handles additions, removals, role changes, and reordering.
     *
     * @param Post $post
     * @param list<array{id: string, role: string, position: int, altText: ?string, caption: ?string, downloadName: ?string}> $submitted
     * @throws InvalidArgumentException
     * @throws \DomainException
     */
    public function reconcileAttachments(Post $post, array $submitted): void
    {
        $existingMap = [];
        foreach ($post->files as $existing) {
            $existingMap[(string) $existing->getFile()->getId()] = $existing;
        }

        $submittedIds = [];
        foreach ($submitted as $item) {
            $fileId = $item['id'];
            $submittedIds[] = $fileId;

            if (! isset($existingMap[$fileId])) {
                throw new InvalidArgumentException("File {$fileId} is not attached to this post.");
            }

            $postFile = $existingMap[$fileId];
            $postFile->setRole(PostFileRole::from($item['role']));
            $postFile->setPosition($item['position']);
            $postFile->setAltText($item['altText'] ?? null);
            $postFile->setCaption($item['caption'] ?? null);
            $postFile->setDownloadName($item['downloadName'] ?? null);
        }

        foreach ($existingMap as $fileId => $postFile) {
            if (! \in_array($fileId, $submittedIds, true)) {
                $post->removeFile($postFile);
            }
        }

        $this->em->flush();
    }

    /**
     * Attach a file to a post with the given role and metadata.
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function attachFile(
        Post $post,
        File $file,
        PostFileRole $role,
        int $position = 0,
        ?string $altText = null,
        ?string $caption = null,
        ?string $downloadName = null,
    ): PostFile {
        $postFile = new PostFile($post, $file, $role, $position);
        $postFile->setAltText($altText);
        $postFile->setCaption($caption);
        $postFile->setDownloadName($downloadName);

        $this->em->persist($postFile);
        $this->em->flush();

        return $postFile;
    }

    /**
     * Remove an attachment from a post without deleting the underlying file.
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
