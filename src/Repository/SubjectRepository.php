<?php

namespace App\Repository;

use App\Entity\Subject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subject::class);
    }
    public function findCommonFirstYearSubjects(){
        return $this->createQueryBuilder('s')
            ->andWhere('s.isCommonFirstYear = :common')
            ->andWhere('s.courseNumber = :courseNumber')
            ->setParameter('common' , true)
            ->setParameter('courseNumber' , 1)
            ->orderBy('s.subjectName' , 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findByFacultyAndCourse(int $facultyId, int $courseNumber): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.faculty', 'f')
            ->where('f.id = :facultyId')
            ->andWhere('s.courseNumber = :courseNumber')
            ->setParameter('facultyId', $facultyId)
            ->setParameter('courseNumber', $courseNumber)
            ->orderBy('s.subjectName', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findAllFirstYearSubjects(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.courseNumber = :courseNumber')
            ->setParameter('courseNumber', 1)
            ->orderBy('s.subjectName', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findSecondYearSubjectsForGroupFaculty(int $facultyId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.faculty', 'f');

        if (in_array($facultyId, [1, 2, 3])) {
            $qb->where('f.id IN (:faculties)')
                ->setParameter('faculties', [1, 2, 3]);
        } elseif (in_array($facultyId, [4, 5, 6])) {
            $qb->where('f.id IN (:faculties)')
                ->setParameter('faculties', [4, 5, 6]);
        } else {
            return [];
        }


        $qb->andWhere('s.courseNumber = :course')
            ->setParameter('course', 2)
            ->orderBy('f.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
    public function findThirdYearSubjectsForGroupFaculty(int $facultyId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.faculty', 'f');

        if (in_array($facultyId, [1, 2])) {
            $qb->where('f.id IN (:faculties)')
                ->setParameter('faculties', [1, 2]);
        } elseif (in_array($facultyId, [3, 4])) {
            $qb->where('f.id IN (:faculties)')
                ->setParameter('faculties', [3, 4]);
        } elseif (in_array($facultyId, [5, 6])) {
            $qb->where('f.id IN (:faculties)')
                ->setParameter('faculties', [5, 6]);
        } else {
            return [];
        }

        $qb->andWhere('s.courseNumber = :course')
            ->setParameter('course', 3)
            ->orderBy('f.id', 'ASC');

        return $qb->getQuery()->getResult();
    }
    public function findFourthYearSubjectsByFaculty(int $facultyId): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.faculty', 'f')
            ->where('f.id = :facultyId')
            ->andWhere('s.courseNumber = :courseNumber')
            ->setParameter('facultyId', $facultyId)
            ->setParameter('courseNumber', 4)
            ->orderBy('s.subjectName', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
