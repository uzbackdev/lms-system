<?php

namespace App\Entity;
use App\Repository\FacultyRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacultyRepository::class)]
class Faculty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: false , unique: true)]
    private string $name;

    #[ORM\OneToMany(mappedBy: 'faculty', targetEntity: Teacher::class, cascade: ['persist', 'remove'])]
    private Collection $teachers;

    #[ORM\OneToMany(mappedBy: 'faculty', targetEntity: Subject::class, cascade: ['persist', 'remove'])]
    private Collection $subjects;

    #[ORM\OneToMany(mappedBy: 'faculty' , targetEntity: Group::class , cascade: ['persist' , 'remove'] )]
    private Collection $groups;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function setGroups(Collection $groups): void
    {
        $this->groups = $groups;
    }


    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function setTeachers(Collection $teachers): void
    {
        $this->teachers = $teachers;
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
