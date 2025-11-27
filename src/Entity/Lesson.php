<?php

namespace App\Entity;

use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GroupSubjectTeacher::class)]
    #[ORM\JoinColumn(nullable: false)]
    private GroupSubjectTeacher $groupSubjectTeacher;

    #[ORM\Column(type: 'integer', enumType: LessonNumber::class)]
    private LessonNumber $number;

    #[ORM\Column(type: 'integer', enumType: WeekDay::class)]
    private WeekDay $day;

    public function getGroup(): Group
    {
        return $this->groupSubjectTeacher->getGroup();
    }

    public function getSubject(): Subject
    {
        return $this->groupSubjectTeacher->getSubject();
    }

    public function getTeacher(): Teacher
    {
        return $this->groupSubjectTeacher->getTeacher();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupSubjectTeacher(): GroupSubjectTeacher
    {
        return $this->groupSubjectTeacher;
    }

    public function getNumber(): LessonNumber
    {
        return $this->number;
    }

    public function getDay(): WeekDay
    {
        return $this->day;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setGroupSubjectTeacher(GroupSubjectTeacher $groupSubjectTeacher): void
    {
        $this->groupSubjectTeacher = $groupSubjectTeacher;
    }

    public function setNumber(LessonNumber $number): void
    {
        $this->number = $number;
    }

    public function setDay(WeekDay $day): void
    {
        $this->day = $day;
    }




}
