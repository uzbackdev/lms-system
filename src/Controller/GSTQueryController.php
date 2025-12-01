<?php

namespace App\Controller;

use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\SubjectRepository;
use App\Repository\TeacherRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/group-subject-teacher')]
class GSTQueryController extends AbstractController
{
    #[Route('/teachers/by-subject/{subjectId}', methods: ['GET'])]
    public function getTeachersBySubject(
        int $subjectId,
        TeacherRepository $teacherRepository,
        SubjectRepository $subjectRepository
    ): JsonResponse {
        $subject = $subjectRepository->find($subjectId);
        if (!$subject) {
            return $this->json(['error' => 'Fan topilmadi'], 404);
        }

        $teachers = $teacherRepository->findTeachersBySubjectWithCommonCheck($subject);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($t) => [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'surname' => $t->getSurname(),
                'faculty' => $t->getFaculty()->getName(),
            ], $teachers)
        ]);
    }

    #[Route('/group/{groupId}', methods: ['GET'])]
    public function getByGroup(int $groupId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findByGroup($groupId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    #[Route('/teacher/{teacherId}', methods: ['GET'])]
    public function getByTeacher(int $teacherId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findByTeacher($teacherId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    #[Route('/subject/{subjectId}', methods: ['GET'])]
    public function getBySubject(int $subjectId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findBySubject($subjectId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }
}
