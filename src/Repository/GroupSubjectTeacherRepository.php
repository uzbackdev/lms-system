<?php

namespace App\Repository;

use App\Entity\GroupSubjectTeacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupSubjectTeacher>
 */
class GroupSubjectTeacherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupSubjectTeacher::class);
    }

    public function findByGroup(int $groupId): array
    {
        return $this->createQueryBuilder('gst')
            ->leftJoin('gst.group', 'g')
            ->leftJoin('gst.subject', 's')
            ->leftJoin('gst.teacher', 't')
            ->leftJoin('t.faculty', 'f')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->select('gst', 'g', 's', 't', 'f')
            ->getQuery()
            ->getResult();
    }

    public function findByTeacher(int $teacherId): array
    {
        return $this->createQueryBuilder('gst')
            ->leftJoin('gst.group', 'g')
            ->leftJoin('gst.subject', 's')
            ->leftJoin('gst.teacher', 't')
            ->leftJoin('g.course', 'c')
            ->where('t.id = :teacherId')
            ->setParameter('teacherId', $teacherId)
            ->select('gst.id', 'g.id as group_id', 'g.number as groupNumber', 'c.number as courseNumber',
                's.subjectName as subjectName', 's.id as subjectId')
            ->getQuery()
            ->getResult();
    }

    public function findBySubject(int $subjectId): array
    {
        return $this->createQueryBuilder('gst')
            ->leftJoin('gst.group', 'g')
            ->leftJoin('gst.subject', 's')
            ->leftJoin('gst.teacher', 't')
            ->leftJoin('g.course', 'c')
            ->where('s.id = :subjectId')
            ->setParameter('subjectId', $subjectId)
            ->select('gst.id', 'g.number as groupNumber', 'c.number as courseNumber',
                't.name as teacherName', 't.surname as teacherSurname')
            ->getQuery()
            ->getResult();
    }

    public function existsByGroupAndSubject(int $groupId, int $subjectId): bool
    {
        $result = $this->createQueryBuilder('gst')
            ->where('gst.group = :groupId')
            ->andWhere('gst.subject = :subjectId')
            ->setParameter('groupId', $groupId)
            ->setParameter('subjectId', $subjectId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }


    public function getTeacherWorkload(int $teacherId): int
    {
        return $this->createQueryBuilder('gst')
            ->select('COUNT(gst.id)')
            ->where('gst.teacher = :teacherId')
            ->setParameter('teacherId', $teacherId)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function canTeacherTakeMoreSubjects(int $teacherId, int $maxHours = 10): bool
    {
        $currentWorkload = $this->getTeacherWorkload($teacherId);
        return $currentWorkload < $maxHours;
    }
    public function findByTeacherAndGroup(int $teacherId, int $groupId): array
    {
        return $this->createQueryBuilder('gst')
            ->leftJoin('gst.group', 'g')
            ->leftJoin('gst.subject', 's')
            ->leftJoin('gst.teacher', 't')
            ->where('t.id = :teacherId')
            ->andWhere('g.id = :groupId')
            ->setParameter('teacherId', $teacherId)
            ->setParameter('groupId', $groupId)
            ->select('gst.id', 's.subjectName as subject_name', 's.id as subject_id')
            ->getQuery()
            ->getResult();
    }
}
