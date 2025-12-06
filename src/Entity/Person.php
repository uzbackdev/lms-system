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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 8, unique: true)]
    #[Assert\NotBlank(message: 'Login kiritilishi shart')]
    #[Assert\Length(
        min: 8,
        max: 8,
        exactMessage: 'Login 8 ta belgidan iborat bo\'lishi kerak'
    )]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9]+$/',
        message: 'Login faqat bosh harfli lotin harflari (A-Z) va raqamlardan (0-9) iborat bo\'lishi kerak'
    )]
    private string $login;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank(message: 'Parol kiritilishi shart')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank(message: 'Ism kiritilishi shart')]
    #[Assert\Length(min: 2, max: 50)]
    private string $name;

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank(message: 'Familiya kiritilishi shart')]
    #[Assert\Length(min: 2, max: 50)]
    private string $surname;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 200)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(max: 20)]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s\-\(\)]+$/',
        message: 'Telefon raqami noto\'g\'ri formatda'
    )]
    private ?string $phone = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {

    }

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

    public function setLogin(string $login): static
    {
        $this->login = strtoupper($login);
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->surname;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }



    public function getPersonType(): PersonType
    {
        if (in_array('ROLE_ADMIN', $this->roles, true)) {
            return PersonType::ADMIN;
        }

        if (in_array('ROLE_TEACHER', $this->roles, true)) {
            return PersonType::TEACHER;
        }

        if (in_array('ROLE_STUDENT', $this->roles, true)) {
            return PersonType::STUDENT;
        }

        return PersonType::STUDENT;
    }

    public function isStudent(): bool
    {
        return in_array('ROLE_STUDENT', $this->roles, true);
    }

    public function isTeacher(): bool
    {
        return in_array('ROLE_TEACHER', $this->roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(string $role): static
    {
        $key = array_search($role, $this->roles, true);

        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
