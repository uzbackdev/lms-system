<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }
    public function findByLogin(string $login): ?Student
    {
        return $this->findOneBy(['login' => strtolower($login)]);
    }
    public function findByGroup(int $groupId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.group = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getResult();
    }

}
