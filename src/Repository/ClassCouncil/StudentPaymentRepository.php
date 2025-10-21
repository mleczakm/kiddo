<?php

declare(strict_types=1);

namespace App\Repository\ClassCouncil;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Ds\Set;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<StudentPayment>
 */
class StudentPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentPayment::class);
    }

    /**
     * @param array<Student> $students
     * @param array<Ulid> $studentPaymentIds
     * @return array<int, StudentPayment>
     */
    public function findForStudents(array $students, array $studentPaymentIds = []): array
    {
        if ($students === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('sp')
            ->innerJoin('sp.student', 's')
            ->andWhere('s.id IN (:students)')
            ->setParameter(
                'students',
                new Set($students)
                    ->map(fn(Student $s): string => $s->getId()->toBinary())
                    ->toArray(),
                ArrayParameterType::BINARY
            )
            ->orderBy('sp.label', 'ASC');

        if ($studentPaymentIds !== []) {
            $qb->andWhere('sp.id IN (:studentPaymentIds)')
                ->setParameter(
                    'studentPaymentIds',
                    new Set($studentPaymentIds)
                        ->map(fn(Ulid $id): string => $id->toBinary())
                        ->toArray(),
                    ArrayParameterType::BINARY
                );
        }

        return $qb
            ->getQuery()
            ->getResult();
    }

    public function existsForStudentAndLabel(Student $student, string $label): bool
    {
        $cnt = (int) $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->andWhere('sp.student = :student')
            ->andWhere('sp.label = :label')
            ->setParameter('student', $student->getId(), 'ulid')
            ->setParameter('label', $label)
            ->getQuery()
            ->getSingleScalarResult();
        return $cnt > 0;
    }

    /**
     * @return array<int, StudentPayment>
     */
    public function findByPayment(Payment $payment): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.payment = :payment')
            ->setParameter('payment', $payment->getId(), 'ulid')
            ->getQuery()
            ->getResult();
    }

    /**
     * Return total and paid counts for a given class and label
     * @return array{total:int, paid:int}
     */
    public function getCountsForClassAndLabel(ClassRoom $classRoom, string $label): array
    {
        // total
        $qb = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id) AS total')
            ->innerJoin('sp.student', 's')
            ->andWhere('s.classRoom = :class')
            ->andWhere('sp.label = :label')
            ->setParameter('class', $classRoom->getId(), 'ulid')
            ->setParameter('label', $label);
        $total = (int) $qb->getQuery()
            ->getSingleScalarResult();

        // paid
        $qb2 = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id) AS paid')
            ->innerJoin('sp.student', 's')
            ->andWhere('s.classRoom = :class')
            ->andWhere('sp.label = :label')
            ->andWhere('sp.status = :paid')
            ->setParameter('class', $classRoom->getId(), 'ulid')
            ->setParameter('label', $label)
            ->setParameter('paid', StudentPayment::STATUS_PAID);
        $paid = (int) $qb2->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'paid' => $paid,
        ];
    }
}
