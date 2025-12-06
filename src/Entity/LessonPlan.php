<?php

namespace App\Entity;

use App\Repository\LessonPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonPlanRepository::class)]
class LessonPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    private Lesson $lesson;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'text')]
    private string $topic;

    #[ORM\OneToMany(mappedBy: 'lessonPlan', targetEntity: LessonMaterial::class, cascade: ['persist', 'remove'])]
    private Collection $materials;

    public function __construct()
    {
        $this->materials = new ArrayCollection();
    }
    public function getId(): ?int { return $this->id; }
    public function getLesson(): Lesson { return $this->lesson; }
    public function setLesson(Lesson $lesson): void { $this->lesson = $lesson; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): void { $this->date = $date; }
    public function getTopic(): string { return $this->topic; }
    public function setTopic(string $topic): void { $this->topic = $topic; }

    public function getMaterials(): Collection
    {
        return $this->materials;
    }

    public function setMaterials(Collection $materials): void
    {
        $this->materials = $materials;
    }
    public function addMaterial(LessonMaterial $material): self
    {
        if (!$this->materials->contains($material)) {
            $this->materials->add($material);
            $material->setLessonPlan($this);
        }

        return $this;
    }

    public function removeMaterial(LessonMaterial $material): self
    {
        if ($this->materials->removeElement($material)) {
            if ($material->getLessonPlan() === $this) {
                $material->setLessonPlan(null);
            }
        }

        return $this;
    }




}
