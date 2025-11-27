<?php

namespace App\Entity;

use App\Enum\PersonType;
use App\Repository\PersonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonRepository::class)]
class Person implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\GeneratedValue]
    #[ORM\Id]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 15, unique: true, nullable: false)]
    #[Assert\Unique]
    #[Assert\NotBlank]
    private string $login;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\Length(min: 5)]
    #[Assert\NotBlank]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];


    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void {}

    public function getUserIdentifier(): string
    {
        return $this->login;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): void
    {
        $this->login = $login;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }


    public function getPersonType(): PersonType
    {
        if (in_array('ROLE_STUDENT', $this->roles)) {
            return PersonType::STUDENT;
        }
        if (in_array('ROLE_TEACHER', $this->roles)) {
            return PersonType::TEACHER;
        }
        if(in_array('ROLE_ADMIN' , $this->roles)){
            return PersonType::ADMIN;
        }


        return PersonType::STUDENT;
    }


    public function isStudent(): bool
    {
        return in_array('ROLE_STUDENT', $this->roles);
    }

    public function isTeacher(): bool
    {
        return in_array('ROLE_TEACHER', $this->roles);
    }

    #[ORM\OneToOne(mappedBy: 'person', targetEntity: Student::class, cascade: ['persist'])]
    private ?Student $student = null;

    #[ORM\OneToOne(mappedBy: 'person', targetEntity: Teacher::class, cascade: ['persist'])]
    private ?Teacher $teacher = null;

    #[ORM\OneToOne(mappedBy: 'person', targetEntity: Admin::class, cascade: ['persist'])]
    private ?Admin $admin = null;

    // ... getter/setter lar
    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): void
    {
        $this->student = $student;
    }

    public function getTeacher(): ?Teacher
    {
        return $this->teacher;
    }

    public function setTeacher(?Teacher $teacher): void
    {
        $this->teacher = $teacher;
    }

    public function getAdmin(): ?Admin
    {
        return $this->admin;
    }

    public function setAdmin(?Admin $admin): void
    {
        $this->admin = $admin;
    }


}
