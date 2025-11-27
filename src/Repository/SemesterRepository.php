<?php

namespace App\Repository;

use App\Entity\Semester;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SemesterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Semester::class);
    }
    public function findActiveSemester(): ?Semester
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :isActive')
            ->setParameter('isActive', true)
            ->getQuery()
            ->getOneOrNullResult();
    }


}
