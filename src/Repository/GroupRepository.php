<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry , Group::class);
    }
    public function findByFacultyAndCourse(int $facultyId, ?int $courseId = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->join('g.course', 'c')
            ->addSelect('c')
            ->andWhere('g.faculty = :facultyId')
            ->setParameter('facultyId', $facultyId);

        if ($courseId) {
            $qb->andWhere('c.id = :courseId')
                ->setParameter('courseId', $courseId);
        }

        return $qb
            ->orderBy('c.number', 'ASC')
            ->addOrderBy('g.groupNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
