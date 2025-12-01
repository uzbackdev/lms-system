<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\Teacher;
use App\Repository\FacultyRepository;
use App\Repository\GroupRepository;
use App\Service\StudentService;
use App\Service\TeacherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/general-admin')]
class PersonCreateController extends AbstractController
{
    #[Route('/create-student', methods: ['POST'])]
    public function createStudent(
        Request $request,
        GroupRepository $groupRepository,
        StudentService $studentAuthService,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $groupId = $data['group_id'] ?? null;
        $name = $data['name'] ?? null;
        $surname = $data['surname'] ?? null;
        $address = $data['address'] ?? null;

        $group = $groupRepository->find($groupId);
        if (!$group) {
            return $this->json("Guruh topilmadi", 404);
        }

        $student = new Student();
        $student->setName($name);
        $student->setSurname($surname);
        $student->setAddress($address);
        $student->setGroup($group);

        $em->persist($student);
        $em->flush();

        $authData = $studentAuthService->createPersonWithAuth($student->getId());
        $person = $authData['person'];

        $student->setPerson($person);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Student yaratildi',
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroupNumber(),
                'course' => $student->getCourseNumber(),
                'login' => $authData['login'],
                'password' => $authData['plain_password']
            ]
        ]);
    }

    #[Route('/create-teacher', methods: ['POST'])]
    public function createTeacher(
        Request $request,
        FacultyRepository $facultyRepository,
        TeacherService $teacherService,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $departmentId = $data['faculty_id'] ?? null;
        $name = $data['name'] ?? null;
        $surname = $data['surname'] ?? null;

        $faculty = $facultyRepository->find($departmentId);
        if (!$faculty) {
            return $this->json(['error' => 'Kafedra topilmadi'], 404);
        }

        $teacher = new Teacher();
        $teacher->setName($name);
        $teacher->setSurname($surname);
        $teacher->setFaculty($faculty);

        $em->persist($teacher);
        $em->flush();

        $authData = $teacherService->createPersonWithAuth($teacher->getId());
        $person = $authData['person'];

        $teacher->setPerson($person);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "O'qituvchi yaratildi",
            'teacher' => [
                'id' => $teacher->getId(),
                'name' => $teacher->getName(),
                'surname' => $teacher->getSurname(),
                'faculty' => $faculty->getName(),
                'login' => $authData['login'],
                'password' => $authData['plain_password']
            ]
        ]);
    }
}
