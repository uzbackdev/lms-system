<?php

namespace App\Entity;

use App\Repository\DeadlineSubmissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeadlineSubmissionRepository::class)]
class DeadlineSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Deadline::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Deadline $deadline;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Student $student;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalFileName;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $submittedAt;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'submitted';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $points = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $teacherComment = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $gradedAt = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTime();
    }
    public function canBeGraded(): bool
    {
        return $this->status === 'submitted' && $this->points === null;
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded' && $this->points !== null;
    }

    public function getGradingMethod(): string
    {
        return $this->isGraded() ? 'PUT' : 'POST';
    }

    public function getDownloadUrl(): string
    {
        return '/uploads/' . $this->fileName;
    }

    public function getGradingInfo(): array
    {
        return [
            'can_grade' => $this->canBeGraded(),
            'is_graded' => $this->isGraded(),
            'grading_method' => $this->getGradingMethod(),
            'max_points' => $this->getDeadline()->getMaxPoints()
        ];
    }



    public function getId(): ?int { return $this->id; }

    public function getDeadline(): Deadline { return $this->deadline; }
    public function setDeadline(Deadline $deadline): void { $this->deadline = $deadline; }

    public function getStudent(): Student { return $this->student; }
    public function setStudent(Student $student): void { $this->student = $student; }

    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): void { $this->fileName = $fileName; }

    public function getOriginalFileName(): string { return $this->originalFileName; }
    public function setOriginalFileName(string $originalFileName): void { $this->originalFileName = $originalFileName; }

    public function getSubmittedAt(): \DateTimeInterface { return $this->submittedAt; }
    public function setSubmittedAt(\DateTimeInterface $submittedAt): void { $this->submittedAt = $submittedAt; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function getPoints(): ?int { return $this->points; }
    public function setPoints(?int $points): void { $this->points = $points; }

    public function getTeacherComment(): ?string { return $this->teacherComment; }
    public function setTeacherComment(?string $teacherComment): void { $this->teacherComment = $teacherComment; }

    public function getGradedAt(): ?\DateTimeInterface { return $this->gradedAt; }
    public function setGradedAt(?\DateTimeInterface $gradedAt): void { $this->gradedAt = $gradedAt; }
}
