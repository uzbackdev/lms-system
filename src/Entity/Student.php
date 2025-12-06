<?php

namespace App\Entity;


use App\Repository\StudentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;

    #[ORM\OneToOne(targetEntity: Person::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', unique: true, nullable: true)]
    private Person $person;

    #[ORM\Column(type: 'integer')]
    private int $nbCount = 0;

    public function getNbCount(): int
    {
        return $this->nbCount;
    }

    public function setNbCount(int $nbCount): void
    {
        $this->nbCount = $nbCount;
    }

    public function incrementNbCount(): void
    {
        $this->nbCount++;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }




    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): void
    {
        $this->person = $person;
    }

    public function getCourseNumber():int{
        return $this->getGroup()->getCourse()->getNumber();
    }
    public function getGroupNumber():int{
        return $this->getGroup()->getGroupNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setGroup(Group $group): void
    {
        $this->group = $group;
    }


}
