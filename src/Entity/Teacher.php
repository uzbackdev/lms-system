<?php

namespace App\Entity;
use App\Repository\TeacherRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeacherRepository::class)]
class Teacher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'teachers')]
    #[ORM\JoinColumn(nullable: true , onDelete: "CASCADE")]
    private Faculty $faculty;


    #[ORM\OneToOne(targetEntity: Person::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', unique: true, nullable: true)]
    private Person $person;

    #[ORM\ManyToMany(targetEntity: Subject::class, inversedBy: 'teachers')]
    private Collection $subjects;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }


    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): void
    {
        $this->person = $person;
    }

    public function getFaculty(): Faculty
    {
        return $this->faculty;
    }

    public function setFaculty(Faculty $faculty): void
    {
        $this->faculty = $faculty;
    }

    public function getSubjects(): Collection
    {
        return $this->subjects;
    }

    public function setSubjects(Collection $subjects): void
    {
        $this->subjects = $subjects;
    }




}
