<?php

namespace App\Entity;

use App\Repository\LessonMaterialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonMaterialRepository::class)]
class LessonMaterial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LessonPlan::class, inversedBy: 'materials')]
    #[ORM\JoinColumn(nullable: false)]
    private LessonPlan $lessonPlan;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\Column(type: 'string', length: 50)]
    private string $fileType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalName;

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }


    public function getId(): ?int { return $this->id; }
    public function getLessonPlan(): LessonPlan { return $this->lessonPlan; }
    public function setLessonPlan(LessonPlan $lessonPlan): void { $this->lessonPlan = $lessonPlan; }
    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): void { $this->fileName = $fileName; }
    public function getFileType(): string { return $this->fileType; }
    public function setFileType(string $fileType): void { $this->fileType = $fileType; }
}
