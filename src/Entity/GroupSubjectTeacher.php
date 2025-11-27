<?php

namespace App\Entity;

use App\Repository\GroupSubjectTeacherRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupSubjectTeacherRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_group_subject', columns: ['group_id', 'subject_id'])]
class GroupSubjectTeacher
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;

    #[ORM\ManyToOne(targetEntity: Subject::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Subject $subject;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Teacher $teacher;

    #[ORM\OneToMany(mappedBy: 'groupSubjectTeacher', targetEntity: Deadline::class, cascade: ['persist', 'remove'])]
    private Collection $deadlines;

    public function getDeadlines(): Collection
    {
        return $this->deadlines;
    }

    public function setDeadlines(Collection $deadlines): void
    {
        $this->deadlines = $deadlines;
    }



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): void
    {
        $this->group = $group;
    }

    public function getSubject(): Subject
    {
        return $this->subject;
    }

    public function setSubject(Subject $subject): void
    {
        $this->subject = $subject;
    }

    public function getTeacher(): Teacher
    {
        return $this->teacher;
    }

    public function setTeacher(Teacher $teacher): void
    {
        $this->teacher = $teacher;
    }
}
