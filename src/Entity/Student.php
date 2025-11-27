<?php

namespace App\Entity;


use App\Repository\StudentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank]
    private string $surname;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank]
    private string $address;

    #[ORM\ManyToOne(inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: false)]
    private Group $group;



    #[ORM\OneToOne(inversedBy: 'student', targetEntity: Person::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'person_id', referencedColumnName: 'id', nullable: true, unique: true)]
    private Person $person;

    #[ORM\Column(type: 'integer')]
    private int $nbCount = 0;

    public function getNbCount(): int
    {
        return $this->nbCount;
    }

    public function setNbCount(int $nbCount): void
    {
        $this->nbCount = $nbCount;
    }

    public function incrementNbCount(): void
    {
        $this->nbCount++;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }




    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): void
    {
        $this->person = $person;
    }

    public function getCourseNumber():int{
        return $this->getGroup()->getCourse()->getNumber();
    }
    public function getGroupNumber():int{
        return $this->getGroup()->getGroupNumber();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): void
    {
        $this->surname = $surname;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function setGroup(Group $group): void
    {
        $this->group = $group;
    }




}
