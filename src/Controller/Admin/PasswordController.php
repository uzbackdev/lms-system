<?php

namespace App\Controller\Admin;

use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
class PasswordController extends AbstractController
{
    #[Route('/teachers/password', methods: ['PUT'])]
    public function changeTeacherPassword(
        Request $request,
        TeacherRepository $teacherRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $teacherId = $data['teacher_id'] ?? null;
        $newPassword = $data['new_password'] ?? null;

        if (!$teacherId) {
            return $this->json(['error' => 'Teacher ID kiritilmagan'], 400);
        }

        $teacher = $teacherRepository->find($teacherId);
        if (!$teacher) {
            return $this->json(['error' => 'O\'qituvchi topilmadi'], 404);
        }

        if (!$newPassword) {
            return $this->json(['error' => 'Yangi parol kiritilmagan'], 400);
        }

        $passwordConstraint = new Assert\Length([
            'min' => 6,
            'minMessage' => 'Parol kamida 6 ta belgidan iborat bo\'lishi kerak'
        ]);

        $errors = $validator->validate($newPassword, $passwordConstraint);
        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 400);
        }

        $person = $teacher->getPerson();
        $hashedPassword = $passwordHasher->hashPassword($person, $newPassword);
        $person->setPassword($hashedPassword);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Parol muvaffaqiyatli yangilandi',
            'teacher' => [
                'id' => $teacher->getId(),
                'name' => $teacher->getName(),
                'surname' => $teacher->getSurname()
            ]
        ]);
    }

    #[Route('/students/password', methods: ['PUT'])]
    public function changeStudentPassword(
        Request $request,
        StudentRepository $studentRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $studentId = $data['student_id'] ?? null;
        $newPassword = $data['new_password'] ?? null;

        if (!$studentId) {
            return $this->json(['error' => 'Student ID kiritilmagan'], 400);
        }

        $student = $studentRepository->find($studentId);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        if (!$newPassword) {
            return $this->json(['error' => 'Yangi parol kiritilmagan'], 400);
        }

        $passwordConstraint = new Assert\Length([
            'min' => 6,
            'minMessage' => 'Parol kamida 6 ta belgidan iborat bo\'lishi kerak'
        ]);

        $errors = $validator->validate($newPassword, $passwordConstraint);
        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 400);
        }

        $person = $student->getPerson();
        $hashedPassword = $passwordHasher->hashPassword($person, $newPassword);
        $person->setPassword($hashedPassword);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Student paroli muvaffaqiyatli yangilandi',
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber()
            ]
        ]);
    }
}
