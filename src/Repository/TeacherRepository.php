<?php

namespace App\Repository;

use App\Entity\Subject;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TeacherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Teacher::class);
    }
    public function findTeachersBySubjectWithCommonCheck(Subject $subject): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.person', 'p');

        if ($subject->isCommonFirstYear()) {
            return $qb
                ->orderBy('p.surname', 'ASC')
                ->addOrderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $qb
            ->join('t.faculty', 'f')
            ->join('App\Entity\Subject', 's', 'WITH', 's.faculty = f')
            ->where('s.id = :subjectId')
            ->setParameter('subjectId', $subject->getId())
            ->orderBy('p.surname', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }


}
