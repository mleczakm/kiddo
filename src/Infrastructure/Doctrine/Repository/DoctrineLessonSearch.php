<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Application\Repository\LessonSearchInterface;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLessonSearch implements LessonSearchInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return list<Lesson>
     */
    #[\Override]
    public function findCancelledInRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var list<Lesson> */
        return $this->em
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->andWhere('l.status = :status')
            ->andWhere('l.schedule BETWEEN :start AND :end')
            ->setParameter('status', Lesson::STATUS_CANCELLED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('l.schedule', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Lesson>
     */
    #[\Override]
    public function findByMetadataTitlePrefix(string $prefix): array
    {
        /** @var list<Lesson> */
        return $this->em
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.metadata', 'm')
            ->where('m.title LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Lesson>
     */
    #[\Override]
    public function findForStaff(
        ?string $titleQuery,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?User $instructor,
        int $limit,
    ): array {
        $qb = $this->em
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->join('l.metadata', 'm')
            ->leftJoin('l.series', 's')
            ->andWhere('l.status = :status')
            ->setParameter('status', Lesson::STATUS_ACTIVE)
            ->orderBy('l.schedule', 'ASC')
            ->setMaxResults($limit);

        if ($titleQuery !== null && trim($titleQuery) !== '') {
            $qb->andWhere('ILIKE(m.title, :query) = TRUE')->setParameter('query', '%' . trim($titleQuery) . '%');
        }

        if ($from !== null) {
            $qb->andWhere('l.schedule >= :from')->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('l.schedule <= :to')->setParameter('to', $to);
        }

        if ($instructor !== null) {
            $qb->andWhere(':instructor MEMBER OF l.instructors OR :instructor MEMBER OF s.instructors')->setParameter(
                'instructor',
                $instructor,
            );
        }

        /** @var list<Lesson> */
        return $qb->getQuery()->getResult();
    }
}
