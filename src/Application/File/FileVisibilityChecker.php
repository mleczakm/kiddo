<?php

declare(strict_types=1);

namespace App\Application\File;

use App\Entity\File;
use App\Entity\PostFileRole;
use App\Entity\PostStatus;
use App\Entity\WorkshopFileRole;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Whether a stored file is safe to serve publicly: a file is public only
 * once it's attached to at least one post that's actually live, never
 * merely because its ULID is known. Workshop attachments have no separate
 * draft/published state (a lesson is visitor-facing as soon as it exists),
 * so any file attached to a workshop is public.
 */
final readonly class FileVisibilityChecker
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function isPublic(File $file, \DateTimeImmutable $now): bool
    {
        foreach ($this->getPostsForFile($file) as $post) {
            if (
                $post['status'] === PostStatus::PUBLISHED
                && $post['publishedAt'] !== null
                && $post['publishedAt'] <= $now
            ) {
                return true;
            }
        }

        return $this->isAttachedToWorkshop($file);
    }

    public function isInline(File $file): bool
    {
        $query = $this->em
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\PostFile', 'pf')
            ->where('pf.file = :file')
            ->andWhere('pf.role = :role')
            ->setParameter('file', $file->getId(), 'ulid')
            ->setParameter('role', PostFileRole::INLINE->value)
            ->setMaxResults(1)
            ->getQuery();

        /** @var list<mixed> $result */
        $result = $query->getResult();

        if (\count($result) > 0) {
            return true;
        }

        return $this->isWorkshopTermsAttachment($file);
    }

    private function isAttachedToWorkshop(File $file): bool
    {
        $query = $this->em
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\WorkshopFile', 'wf')
            ->where('wf.file = :file')
            ->setParameter('file', $file->getId(), 'ulid')
            ->setMaxResults(1)
            ->getQuery();

        /** @var list<mixed> $result */
        $result = $query->getResult();

        return \count($result) > 0;
    }

    private function isWorkshopTermsAttachment(File $file): bool
    {
        $query = $this->em
            ->createQueryBuilder()
            ->select('1')
            ->from('App\Entity\WorkshopFile', 'wf')
            ->where('wf.file = :file')
            ->andWhere('wf.role = :role')
            ->setParameter('file', $file->getId(), 'ulid')
            ->setParameter('role', WorkshopFileRole::TERMS_OF_USE->value)
            ->setMaxResults(1)
            ->getQuery();

        /** @var list<mixed> $result */
        $result = $query->getResult();

        return \count($result) > 0;
    }

    /** @return list<array{status: PostStatus, publishedAt: ?\DateTimeImmutable}> */
    private function getPostsForFile(File $file): array
    {
        $query = $this->em
            ->createQueryBuilder()
            ->select('p.status, p.publishedAt')
            ->from('App\Entity\PostFile', 'pf')
            ->join('pf.post', 'p')
            ->where('pf.file = :file')
            ->setParameter('file', $file->getId(), 'ulid')
            ->getQuery();

        /** @var list<array{status: PostStatus, publishedAt: ?\DateTimeImmutable}> */
        return $query->getResult();
    }
}
