<?php

namespace App\Repository;

use App\Entity\Lesson;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }
    public function isTeacherInThisTimeBusy(int $teacherId, WeekDay $day, LessonNumber $lessonNumber): bool
    {
        return $this->createQueryBuilder('l')
                ->select('COUNT(l.id)')
                ->join('l.groupSubjectTeacher', 'gst')
                ->join('gst.teacher', 't')
                ->where('t.id = :teacherId')
                ->andWhere('l.day = :day')
                ->andWhere('l.number = :lessonNumber')
                ->setParameter('teacherId', $teacherId)
                ->setParameter('day', $day)
                ->setParameter('lessonNumber', $lessonNumber)
                ->getQuery()
                ->getSingleScalarResult() > 0;
    }
    public function getSubjectLessonCount(int $gstId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.groupSubjectTeacher = :gstId')
            ->setParameter('gstId', $gstId)
            ->getQuery()
            ->getSingleScalarResult();
    }
    public function findByTeacher(int $teacherId): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.teacher', 't')
            ->where('t.id = :teacherId')
            ->setParameter('teacherId', $teacherId)
            ->getQuery()
            ->getResult();
    }
    public function findByGroup(int $groupId): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getResult();
    }
    public function isTeacherBusy(int $teacherId, WeekDay $day, LessonNumber $number): bool
    {
        return $this->createQueryBuilder('l')
                ->select('COUNT(l.id)')
                ->join('l.groupSubjectTeacher', 'gst')
                ->join('gst.teacher', 't')
                ->where('t.id = :teacherId')
                ->andWhere('l.day = :day')
                ->andWhere('l.number = :lessonNumber')
                ->setParameter('teacherId', $teacherId)
                ->setParameter('day', $day)
                ->setParameter('lessonNumber', $number)
                ->getQuery()
                ->getSingleScalarResult() > 0;
    }
    public function isGroupBusy(int $groupId, WeekDay $day, LessonNumber $number): bool
    {
        return $this->createQueryBuilder('l')
                ->select('COUNT(l.id)')
                ->join('l.groupSubjectTeacher', 'gst')
                ->join('gst.group', 'g')
                ->where('g.id = :groupId')
                ->andWhere('l.day = :day')
                ->andWhere('l.number = :lessonNumber')
                ->setParameter('groupId', $groupId)
                ->setParameter('day', $day)
                ->setParameter('lessonNumber', $number)
                ->getQuery()
                ->getSingleScalarResult() > 0;
    }
    public function getWeeklySubjectCount(int $gstId): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.groupSubjectTeacher = :gstId')
            ->setParameter('gstId', $gstId)
            ->getQuery()
            ->getSingleScalarResult();
    }
    public function getDailyGroupLessons(int $groupId, WeekDay $day): int
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->where('g.id = :groupId')
            ->andWhere('l.day = :day')
            ->setParameter('groupId', $groupId)
            ->setParameter('day', $day)
            ->getQuery()
            ->getSingleScalarResult();
    }
    public function findByGroupSubjectAndTeacher(int $groupId, int $subjectId, int $teacherId): array
    {
        $query = $this->createQueryBuilder('l')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->join('gst.subject', 's')
            ->join('gst.teacher', 't')
            ->where('g.id = :groupId')
            ->andWhere('s.id = :subjectId')
            ->andWhere('t.id = :teacherId')
            ->setParameter('groupId', $groupId)
            ->setParameter('subjectId', $subjectId)
            ->setParameter('teacherId', $teacherId)
            ->getQuery();


        $sql = $query->getSQL();
        $params = $query->getParameters();
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));

        $result = $query->getResult();
        error_log("Result count: " . count($result));

        return $result;
    }
    public function findLessonsByDate(\DateTimeInterface $date): array
    {
        $dayName = strtolower($date->format('l')); // 'monday', 'tuesday', etc.

        return $this->createQueryBuilder('l')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.group', 'g')
            ->join('gst.subject', 's')
            ->join('gst.teacher', 't')
            ->where('l.day = :day')
            ->setParameter('day', WeekDay::from($dayName))
            ->select('l', 'gst', 'g', 's', 't')
            ->getQuery()
            ->getResult();
    }

    public function findTeacherLessonsByDate(int $teacherId, \DateTimeInterface $date): array
    {
        $dayName = strtolower($date->format('l'));

        return $this->createQueryBuilder('l')
            ->join('l.groupSubjectTeacher', 'gst')
            ->join('gst.teacher', 't')
            ->join('gst.group', 'g')
            ->join('gst.subject', 's')
            ->where('t.id = :teacherId')
            ->andWhere('l.day = :day')
            ->setParameter('teacherId', $teacherId)
            ->setParameter('day', WeekDay::from($dayName))
            ->select('l', 'gst', 'g', 's')
            ->getQuery()
            ->getResult();
    }


}
