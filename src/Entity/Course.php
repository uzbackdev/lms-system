<?php

namespace App\Entity;

use App\Enum\CourseWeeklyLimit;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'integer' , nullable: false , unique: true)]
    #[Assert\Range(min: 1 , max: 4)]
    private int $number;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Group::class, cascade: ['persist', 'remove'])]
    private Collection $groups;

    private CourseWeeklyLimit $maxWeeklyLessons;

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function setGroups(Collection $groups): void
    {
        $this->groups = $groups;
    }

    public function getMaxWeeklyLessons(): CourseWeeklyLimit
    {
        return $this->maxWeeklyLessons;
    }

    public function setMaxWeeklyLessons(CourseWeeklyLimit $maxWeeklyLessons): void
    {
        $this->maxWeeklyLessons = $maxWeeklyLessons;
    }


}
