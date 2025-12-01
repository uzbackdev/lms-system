<?php

namespace App\Controller;

use App\Entity\Person;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\StudentRepository;
use App\Service\StudentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/student')]
class StudentScheduleController extends AbstractController
{
    #[Route('/schedule', methods: ['GET'])]
    public function getMyCurrentWeekSchedule(
        StudentRepository $studentRepository,
        StudentService $studentService
    ): JsonResponse {
        $person = $this->getUser();
        $student = $studentRepository->findOneBy(['person' => $person]);

        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        $groupId = $student->getGroup()->getId();
        $schedule = $studentService->getCurrentWeekSchedule($groupId);

        return $this->json(['success' => true, 'schedule' => $schedule]);
    }

    #[Route('/my-subjects-teachers', methods: ['GET'])]
    public function getMySubjectsAndTeachers(
        StudentRepository $studentRepository,
        GroupSubjectTeacherRepository $gstRepository
    ): JsonResponse {
        /** @var Person $person */
        $person = $this->getUser();

        if (!$person) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $student = $studentRepository->findOneBy(['person' => $person]);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        $groupId = $student->getGroup()->getId();
        $subjectsTeachers = $gstRepository->findByGroup($groupId);

        $data = [];
        foreach ($subjectsTeachers as $gst) {
            $data[] = [
                'id' => $gst->getId(),
                'subject' => [
                    'id' => $gst->getSubject()->getId(),
                    'name' => $gst->getSubject()->getSubjectName(),
                    'course_number' => $gst->getSubject()->getCourseNumber(),
                ],
                'teacher' => [
                    'id' => $gst->getTeacher()->getId(),
                    'name' => $gst->getTeacher()->getName(),
                    'surname' => $gst->getTeacher()->getSurname(),
                    'faculty' => $gst->getTeacher()->getFaculty()->getName(),
                ],
                'group' => [
                    'id' => $gst->getGroup()->getId(),
                    'number' => $gst->getGroup()->getGroupNumber(),
                    'course' => $gst->getGroup()->getCourse()->getNumber(),
                ]
            ];
        }

        return $this->json([
            'success' => true,
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber(),
                'course' => $student->getGroup()->getCourse()->getNumber(),
            ],
            'subjects_teachers' => $data
        ]);
    }
}
