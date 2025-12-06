<?php

namespace App\Controller\Admin;

use App\Enum\PersonType;
use App\Repository\CourseRepository;
use App\Repository\FacultyRepository;
use App\Repository\GroupRepository;
use App\Repository\PersonRepository;
use App\Service\PersonCreateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class PersonCreateController extends AbstractController
{
    #[Route('/create-person', methods: ['POST'])]
    public function createPerson(
        Request $request,
        PersonCreateService $personCreateService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $result = $personCreateService->createPerson($data);

        if (!$result['success']) {
            return $this->json($result, 400);
        }

        return $this->json($result, 201);
    }

    #[Route('/generate-login', methods: ['GET'])]
    public function generateLogin(PersonCreateService $personCreateService): JsonResponse
    {
        $login = $personCreateService->generateLogin();

        return $this->json([
            'login' => $login,
            'message' => 'Login  ta belgidan iborat (A-Z harflari va 0-9 raqamlari)'
        ]);
    }

    #[Route('/generate-password', methods: ['GET'])]
    public function generatePassword(PersonCreateService $personCreateService): JsonResponse
    {
        $password = $personCreateService->generatePassword();

        return $this->json([
            'password' => $password,
            'message' => 'Parol 8 ta belgidan iborat (a-z harflari va 0-9 raqamlari)'
        ]);
    }

    #[Route('/person-types', methods: ['GET'])]
    public function getPersonTypes(): JsonResponse
    {
        $types = [];
        foreach (PersonType::cases() as $type) {
            $types[] = [
                'value' => $type->value,
                'label' => match($type) {
                    PersonType::STUDENT => 'Talaba',
                    PersonType::TEACHER => "O'qituvchi",
                    PersonType::ADMIN => 'Admin',
                }
            ];
        }

        return $this->json($types);
    }

    #[Route('/faculties', methods: ['GET'])]
    public function getFaculties(FacultyRepository $facultyRepository): JsonResponse
    {
        $faculties = $facultyRepository->findAll();
        $result = [];

        foreach ($faculties as $faculty) {
            $result[] = [
                'id' => $faculty->getId(),
                'name' => $faculty->getName()
            ];
        }

        return $this->json($result);
    }

    #[Route('/courses', methods: ['GET'])]
    public function getCourses(CourseRepository $courseRepository): JsonResponse
    {
        $courses = $courseRepository->findAll();
        $result = [];

        foreach ($courses as $course) {
            $result[] = [
                'id' => $course->getId(),
                'number' => $course->getNumber()
            ];
        }

        return $this->json($result);
    }

    #[Route('/faculty/{facultyId}/groups', methods: ['GET'])]
    public function getFacultyGroups(
        int $facultyId,
        GroupRepository $groupRepository,
        FacultyRepository $facultyRepository,
        Request $request
    ): JsonResponse {
        $faculty = $facultyRepository->find($facultyId);
        if (!$faculty) {
            return $this->json(['error' => 'Fakultet topilmadi'], 404);
        }


        $courseId = $request->query->getInt('course_id');


        $groups = $groupRepository->findByFacultyAndCourse($facultyId, $courseId ?: null);

        $result = [];
        foreach ($groups as $group) {
            $result[] = [
                'id' => $group->getId(),
                'number' => $group->getGroupNumber(),
                'course' => [
                    'id' => $group->getCourse()->getId(),
                    'number' => $group->getCourse()->getNumber()
                ]
            ];
        }

        return $this->json([
            'faculty' => [
                'id' => $faculty->getId(),
                'name' => $faculty->getName()
            ],
            'course_filter' => $courseId ?: 'all',
            'groups' => $result
        ]);
    }

    #[Route('/person/{personId}', methods: ['GET'])]
    public function getPerson(
        int $personId,
        PersonRepository $personRepository
    ): JsonResponse {
        $person = $personRepository->find($personId);

        if (!$person) {
            return $this->json(['error' => 'Foydalanuvchi topilmadi'], 404);
        }

        $response = [
            'id' => $person->getId(),
            'login' => $person->getLogin(),
            'name' => $person->getName(),
            'surname' => $person->getSurname(),
            'address' => $person->getAddress(),
            'phone' => $person->getPhone(),
            'roles' => $person->getRoles(),
            'person_type' => $person->getPersonType()->value,
            'created_at' => $person->getCreatedAt()->format('Y-m-d H:i:s')
        ];

        return $this->json($response);
    }

}
