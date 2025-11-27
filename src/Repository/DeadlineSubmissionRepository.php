<?php

namespace App\Repository;

use App\Entity\DeadlineSubmission;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeadlineSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeadlineSubmission::class);
    }

    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('ds')
            ->join('ds.deadline', 'd')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('gst.subject', 's')
            ->where('ds.student = :studentId')
            ->setParameter('studentId', $studentId)
            ->orderBy('ds.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByDeadlineAndStudent(int $deadlineId, int $studentId): ?DeadlineSubmission
    {
        return $this->createQueryBuilder('ds')
            ->where('ds.deadline = :deadlineId')
            ->andWhere('ds.student = :studentId')
            ->setParameter('deadlineId', $deadlineId)
            ->setParameter('studentId', $studentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ==================== YANGI METODLAR ====================

    /**
     * Ma'lum bir GST (GroupSubjectTeacher) bo'yicha barcha submissions larni olish
     */
    public function findByGST(int $gstId): array
    {
        return $this->createQueryBuilder('ds')
            ->join('ds.deadline', 'd')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('ds.student', 's')
            ->join('gst.group', 'g')
            ->where('gst.id = :gstId')
            ->setParameter('gstId', $gstId)
            ->orderBy('s.surname', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('d.deadlineDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Teacher va Submission ni tekshirish (ruxsat tekshirish uchun)
     */
    public function findOneByTeacherAndSubmission(int $submissionId, int $teacherId): ?DeadlineSubmission
    {
        return $this->createQueryBuilder('ds')
            ->join('ds.deadline', 'd')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('gst.teacher', 't')
            ->where('ds.id = :submissionId')
            ->andWhere('t.id = :teacherId')
            ->setParameter('submissionId', $submissionId)
            ->setParameter('teacherId', $teacherId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Guruh bo'yicha barcha submissions larni olish
     */
    public function findByGroup(int $groupId): array
    {
        return $this->createQueryBuilder('ds')
            ->join('ds.deadline', 'd')
            ->join('d.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->join('ds.student', 's')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->orderBy('s.surname', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->addOrderBy('d.deadlineDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ma'lum bir student va GST bo'yicha submissions larni olish
     */
    public function findByStudentAndGST(int $studentId, int $gstId): array
    {
        return $this->createQueryBuilder('ds')
            ->join('ds.deadline', 'd')
            ->join('d.groupSubjectTeacher', 'gst')
            ->where('ds.student = :studentId')
            ->andWhere('gst.id = :gstId')
            ->setParameter('studentId', $studentId)
            ->setParameter('gstId', $gstId)
            ->orderBy('d.deadlineDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
