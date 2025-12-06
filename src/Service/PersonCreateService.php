<?php

namespace App\Service;

use App\Entity\Person;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\Admin;
use App\Enum\PersonType;
use App\Repository\FacultyRepository;
use App\Repository\GroupRepository;
use App\Repository\CourseRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PersonCreateService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private EntityManagerInterface $em,
        private FacultyRepository $facultyRepository,
        private GroupRepository $groupRepository,
        private CourseRepository $courseRepository,
        private PersonRepository $personRepository
    ) {}

    public function createPerson(array $data): array
    {

        $validationResult = $this->validateData($data);
        if (!$validationResult['is_valid']) {
            return [
                'success' => false,
                'errors' => $validationResult['errors']
            ];
        }


        $person = $this->createPersonEntity($data);


        $specificEntity = $this->createSpecificEntity(
            PersonType::from($data['person_type']),
            $person,
            $data
        );

        if (!$specificEntity['success']) {
            return $specificEntity;
        }


        $this->em->persist($person);
        if (isset($specificEntity['entity'])) {
            $this->em->persist($specificEntity['entity']);
        }
        $this->em->flush();


        return $this->buildResponse($person, $specificEntity, $data);
    }

    private function validateData(array $data): array
    {
        $errors = [];


        $requiredFields = ['name', 'surname', 'login', 'password', 'person_type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst($field) . ' kiritilmagan';
            }
        }


        try {
            $personType = PersonType::from($data['person_type']);
        } catch (\ValueError $e) {
            $errors[] = 'Noto\'g\'ri foydalanuvchi turi';
        }

        // Login format
        if (!empty($data['login'])) {
            if (!preg_match('/^[A-Z0-9]{8}$/', $data['login'])) {
                $errors[] = 'Login 8 ta belgidan iborat bo\'lishi kerak (faqat A-Z harflari va 0-9 raqamlari)';
            }
        }

        // Parol format
        if (!empty($data['password'])) {
            if (!preg_match('/^[a-z0-9]{8}$/', $data['password'])) {
                $errors[] = 'Parol 8 ta belgidan iborat bo\'lishi kerak (faqat a-z harflari va 0-9 raqamlari)';
            }
        }


        if (!empty($data['login'])) {
            $existingPerson = $this->personRepository->findOneBy(['login' => $data['login']]);
            if ($existingPerson) {
                $errors[] = 'Bu login allaqachon mavjud';
            }
        }


        if (isset($personType)) {
            $roleErrors = $this->validateRoleSpecificData($personType, $data);
            $errors = array_merge($errors, $roleErrors);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    private function validateRoleSpecificData(PersonType $personType, array $data): array
    {
        $errors = [];

        switch ($personType) {
            case PersonType::TEACHER:
                if (empty($data['faculty_id'])) {
                    $errors[] = 'Fakultet tanlanmagan';
                }
                break;

            case PersonType::STUDENT:
                $required = ['faculty_id', 'group_id', 'course_id'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' tanlanmagan';
                    }
                }
                break;
        }

        return $errors;
    }

    private function createPersonEntity(array $data): Person
    {
        $person = new Person();
        $person->setLogin($data['login']);
        $person->setName($data['name']);
        $person->setSurname($data['surname']);
        $person->setAddress($data['address'] ?? null);
        $person->setPhone($data['phone'] ?? null);

        $role = match(PersonType::from($data['person_type'])) {
            PersonType::STUDENT => 'ROLE_STUDENT',
            PersonType::TEACHER => 'ROLE_TEACHER',
            PersonType::ADMIN => 'ROLE_ADMIN',
        };
        $person->setRoles([$role]);

        $hashedPassword = $this->passwordHasher->hashPassword($person, $data['password']);
        $person->setPassword($hashedPassword);

        return $person;
    }

    private function createSpecificEntity(PersonType $personType, Person $person, array $data): array
    {
        switch ($personType) {
            case PersonType::TEACHER:
                return $this->createTeacherEntity($person, $data);

            case PersonType::STUDENT:
                return $this->createStudentEntity($person, $data);

            case PersonType::ADMIN:
                return $this->createAdminEntity($person);

            default:
                return ['success' => false, 'error' => 'Noto\'g\'ri foydalanuvchi turi'];
        }
    }

    private function createTeacherEntity(Person $person, array $data): array
    {
        $faculty = $this->facultyRepository->find($data['faculty_id']);
        if (!$faculty) {
            return ['success' => false, 'error' => 'Fakultet topilmadi'];
        }

        $teacher = new Teacher();
        $teacher->setPerson($person);
        $teacher->setFaculty($faculty);

        return [
            'success' => true,
            'entity' => $teacher,
            'type' => 'teacher',
            'faculty' => $faculty
        ];
    }

    private function createStudentEntity(Person $person, array $data): array
    {
        $faculty = $this->facultyRepository->find($data['faculty_id']);
        $group = $this->groupRepository->find($data['group_id']);
        $course = $this->courseRepository->find($data['course_id']);

        if (!$faculty || !$group || !$course) {
            return ['success' => false, 'error' => 'Fakultet, guruh yoki kurs topilmadi'];
        }


        if ($group->getFaculty()->getId() !== $faculty->getId()) {
            return ['success' => false, 'error' => 'Tanlangan guruh ushbu fakultetga tegishli emas'];
        }


        if ($group->getCourse()->getId() !== $course->getId()) {
            return ['success' => false, 'error' => 'Tanlangan guruh ushbu kursga tegishli emas'];
        }

        $student = new Student();
        $student->setPerson($person);
        $student->setGroup($group);


        return [
            'success' => true,
            'entity' => $student,
            'type' => 'student',
            'faculty' => $faculty,
            'group' => $group,
            'course' => $course
        ];
    }

    private function createAdminEntity(Person $person): array
    {
        $admin = new Admin();
        $admin->setPerson($person);

        return [
            'success' => true,
            'entity' => $admin,
            'type' => 'admin'
        ];
    }

    private function buildResponse(Person $person, array $specificEntity, array $data): array
    {
        $personType = PersonType::from($data['person_type']);
        $response = [
            'success' => true,
            'message' => 'Foydalanuvchi muvaffaqiyatli yaratildi',
            'person' => [
                'id' => $person->getId(),
                'login' => $person->getLogin(),
                'name' => $person->getName(),
                'surname' => $person->getSurname(),
                'person_type' => $personType->value,
                'role' => $person->getRoles()[0],
                'plain_password' => $data['password']
            ]
        ];


        switch ($personType) {
            case PersonType::TEACHER:
                $response['teacher'] = [
                    'id' => $specificEntity['entity']->getId(),
                    'faculty' => [
                        'id' => $specificEntity['faculty']->getId(),
                        'name' => $specificEntity['faculty']->getName()
                    ]
                ];
                break;

            case PersonType::STUDENT:
                $response['student'] = [
                    'id' => $specificEntity['entity']->getId(),
                    'course' => [
                        'id' => $specificEntity['course']->getId(),
                        'number' => $specificEntity['course']->getNumber()
                    ],
                    'faculty' => [
                        'id' => $specificEntity['faculty']->getId(),
                        'name' => $specificEntity['faculty']->getName()
                    ],
                    'group' => [
                        'id' => $specificEntity['group']->getId(),
                        'number' => $specificEntity['group']->getGroupNumber()
                    ]
                ];
                break;

            case PersonType::ADMIN:
                $response['admin'] = [
                    'id' => $specificEntity['entity']->getId()
                ];
                break;
        }

        return $response;
    }


    public function generateLogin(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $login = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < 8; $i++) {
            $login .= $characters[random_int(0, $max)];
        }

        return $login;
    }

    public function generatePassword(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < 8; $i++) {
            $password .= $characters[random_int(0, $max)];
        }

        if (!preg_match('/[0-9]/', $password)) {
            $password[7] = (string) random_int(0, 9);
        }

        return $password;
    }
}
