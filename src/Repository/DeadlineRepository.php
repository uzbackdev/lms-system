<?php

namespace App\Repository;

use App\Entity\Deadline;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeadlineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deadline::class);
    }

    public function getTotalPointsForSubject(int $gstId, ?int $excludeDeadlineId = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('SUM(d.maxPoints) as totalPoints')
            ->where('d.groupSubjectTeacher = :gstId')
            ->setParameter('gstId', $gstId);

        if ($excludeDeadlineId) {
            $qb->andWhere('d.id != :excludeId')
                ->setParameter('excludeId', $excludeDeadlineId);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result ? (int)$result : 0;
    }

    public function findByTeacherAndGroup(int $teacherId, int $groupId): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('gst.teacher', 't')
            ->join('gst.group', 'g')
            ->where('t.id = :teacherId')
            ->andWhere('g.id = :groupId')
            ->setParameter('teacherId', $teacherId)
            ->setParameter('groupId', $groupId)
            ->orderBy('d.deadlineDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bir xil sana va guruh/fan uchun deadline mavjudligini tekshiradi
     */
    public function findByGroupSubjectAndDate(int $gstId, string $date, ?int $excludeDeadlineId = null): ?Deadline
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.groupSubjectTeacher = :gstId')
            ->andWhere('d.deadlineDate = :date')
            ->setParameter('gstId', $gstId)
            ->setParameter('date', $date);

        if ($excludeDeadlineId) {
            $qb->andWhere('d.id != :excludeId')
                ->setParameter('excludeId', $excludeDeadlineId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
    public function findByGroup(int $groupId): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->join('gst.subject', 's')
            ->join('gst.teacher', 't')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->orderBy('d.deadlineDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
