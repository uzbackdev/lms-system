<?php

namespace App\Entity;

use App\Repository\DeadlineRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeadlineRepository::class)]
#[ORM\UniqueConstraint(
    name: 'unique_teacher_group_subject_date',
    columns: ['group_subject_teacher_id', 'deadline_date']
)]
class Deadline
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GroupSubjectTeacher::class)]
    #[ORM\JoinColumn(nullable: false)]
    private GroupSubjectTeacher $groupSubjectTeacher;

    #[ORM\ManyToOne(targetEntity: Semester::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Semester $semester;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $originalFileName = null;

    #[ORM\Column(type: 'integer')]
    private int $maxPoints;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $deadlineDate;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }


    public function getId(): ?int { return $this->id; }
    public function getGroupSubjectTeacher(): GroupSubjectTeacher { return $this->groupSubjectTeacher; }
    public function setGroupSubjectTeacher(GroupSubjectTeacher $gst): void { $this->groupSubjectTeacher = $gst; }


    public function getSemester(): Semester { return $this->semester; }
    public function setSemester(Semester $semester): void { $this->semester = $semester; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): void { $this->description = $description; }
    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $fileName): void { $this->fileName = $fileName; }
    public function getOriginalFileName(): ?string { return $this->originalFileName; }
    public function setOriginalFileName(?string $originalFileName): void { $this->originalFileName = $originalFileName; }
    public function getMaxPoints(): int { return $this->maxPoints; }
    public function setMaxPoints(int $maxPoints): void { $this->maxPoints = $maxPoints; }

    public function getDeadlineDate(): \DateTimeInterface { return $this->deadlineDate; }
    public function setDeadlineDate(\DateTimeInterface $deadlineDate): void {

        $this->deadlineDate = \DateTime::createFromFormat('Y-m-d', $deadlineDate->format('Y-m-d'));
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
