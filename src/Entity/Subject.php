<?php

namespace App\Entity;

use App\Repository\SubjectRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubjectRepository::class)]
class Subject
{
    #[ORM\Column]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'subject_name', nullable: false , unique: true)]
    private string $subjectName;

    #[ORM\ManyToMany(targetEntity: Teacher::class, mappedBy: 'subjects')]
    private Collection $teachers;

    #[ORM\ManyToOne(inversedBy: 'subjects')]
    #[ORM\JoinColumn(nullable: false , onDelete: "CASCADE")]
    private Faculty $faculty;

    #[ORM\Column(name: 'course_number' , nullable: false)]
    #[Assert\Range(min: 1 , max: 4)]
    #[Assert\NotBlank]
    private int $courseNumber;

    #[ORM\Column(type: 'boolean')]
    private bool $isCommonFirstYear = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSubjectName(): string
    {
        return $this->subjectName;
    }

    public function setSubjectName(string $subjectName): void
    {
        $this->subjectName = $subjectName;
    }

    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function setTeachers(Collection $teachers): void
    {
        $this->teachers = $teachers;
    }

    public function getFaculty(): Faculty
    {
        return $this->faculty;
    }

    public function setFaculty(Faculty $faculty): void
    {
        $this->faculty = $faculty;
    }



    public function getCourseNumber(): int
    {
        return $this->courseNumber;
    }

    public function setCourseNumber(int $courseNumber): void
    {
        $this->courseNumber = $courseNumber;
    }

    public function isCommonFirstYear(): bool
    {
        return $this->isCommonFirstYear;
    }

    public function setIsCommonFirstYear(bool $isCommonFirstYear): void
    {
        $this->isCommonFirstYear = $isCommonFirstYear;
    }



}
