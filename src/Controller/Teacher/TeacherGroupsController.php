<?php

namespace App\Controller\Teacher;

use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\TeacherRepository;
use App\Service\TeacherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teacher')]
class TeacherGroupsController extends AbstractController
{
    #[Route('/my-groups', methods: ['GET'])]
    public function getMyGroups(
        GroupSubjectTeacherRepository $gstRepository,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $teacherGSTs = $gstRepository->findByTeacher($teacher->getId());

        $uniqueGroups = [];
        foreach ($teacherGSTs as $gst) {
            $groupId = $gst['group_id'];
            if (!isset($uniqueGroups[$groupId])) {
                $uniqueGroups[$groupId] = [
                    'group_id' => $groupId,
                    'group_number' => $gst['groupNumber'],
                    'course_number' => $gst['courseNumber'],
                    'subjects_count' => 0
                ];
            }
            $uniqueGroups[$groupId]['subjects_count']++;
        }

        return $this->json([
            'success' => true,
            'groups' => array_values($uniqueGroups)
        ]);
    }

    #[Route('/my-groups/{groupId}/subjects', methods: ['GET'])]
    public function getGroupSubjects(
        int $groupId,
        GroupSubjectTeacherRepository $gstRepository,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $groupSubjects = $gstRepository->findByTeacherAndGroup($teacher->getId(), $groupId);

        return $this->json([
            'success' => true,
            'subjects' => $groupSubjects
        ]);
    }

    #[Route('/groups/{groupId}/subjects/{subjectId}/students', methods: ['GET'])]
    public function getGroupStudents(
        int $groupId,
        int $subjectId,
        TeacherService $teacherService,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        try {
            $data = $teacherService->getGroupStudents($teacher->getId(), $groupId, $subjectId);

            $studentsData = [];
            foreach ($data['students'] as $student) {
                $studentsData[] = [
                    'id' => $student->getId(),
                    'name' => $student->getPerson()->getName(),
                    'surname' => $student->getPerson()->getSurname(),
                    'login' => $student->getPerson()->getLogin(),
                    'group_number' => $data['group']->getGroupNumber(),
                    'course' => $data['group']->getCourse()->getNumber(),
                    'faculty' => $data['group']->getFaculty()->getName()
                ];
            }

            return $this->json([
                'success' => true,
                'group' => [
                    'id' => $data['group']->getId(),
                    'group_number' => $data['group']->getGroupNumber(),
                    'course' => $data['group']->getCourse()->getNumber(),
                    'faculty' => $data['group']->getFaculty()->getName()
                ],
                'subject' => [
                    'id' => $data['subject']->getId(),
                    'name' => $data['subject']->getSubjectName()
                ],
                'students' => $studentsData
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
