<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Lesson;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    public function findAttendanceByLessonAndDate(int $lessonId, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.lesson', 'l')
            ->join('a.student', 's')
            ->where('l.id = :lessonId')
            ->andWhere('a.date = :date')
            ->setParameter('lessonId', $lessonId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    public function findStudentAttendanceForLesson(int $studentId, int $lessonId, \DateTimeInterface $date): ?Attendance
    {
        return $this->createQueryBuilder('a')
            ->where('a.student = :studentId')
            ->andWhere('a.lesson = :lessonId')
            ->andWhere('a.date = :date')
            ->setParameter('studentId', $studentId)
            ->setParameter('lessonId', $lessonId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getStudentNbCount(int $studentId): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.student = :studentId')
            ->andWhere('a.status = :status')
            ->setParameter('studentId', $studentId)
            ->setParameter('status', 'absent')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
