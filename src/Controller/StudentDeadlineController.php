<?php

namespace App\Controller;

use App\Entity\Person;
use App\Repository\DeadlineRepository;
use App\Repository\DeadlineSubmissionRepository;
use App\Repository\StudentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/student')]
class StudentDeadlineController extends AbstractController
{
    private function calculateDaysRemaining(\DateTimeInterface $deadlineDate): int
    {
        $now = new \DateTime();
        $interval = $now->diff($deadlineDate);
        return (int)$interval->format('%r%a');
    }

    private function canSubmitDeadline(\DateTimeInterface $deadlineDate): bool
    {
        $now = new \DateTime();
        return $now <= $deadlineDate;
    }

    #[Route('/deadlines', methods: ['GET'])]
    public function getMyDeadlines(
        StudentRepository $studentRepository,
        DeadlineRepository $deadlineRepository,
        DeadlineSubmissionRepository $submissionRepository
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
        $deadlines = $deadlineRepository->findByGroup($groupId);

        $deadlinesData = [];
        foreach ($deadlines as $deadline) {
            $submission = $submissionRepository->findOneByDeadlineAndStudent(
                $deadline->getId(),
                $student->getId()
            );

            $deadlinesData[] = [
                'id' => $deadline->getId(),
                'title' => $deadline->getTitle(),
                'description' => $deadline->getDescription(),
                'max_points' => $deadline->getMaxPoints(),
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'),
                'file_name' => $deadline->getOriginalFileName(),
                'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName(),
                'teacher_name' => $deadline->getGroupSubjectTeacher()->getTeacher()->getName() . ' ' .
                    $deadline->getGroupSubjectTeacher()->getTeacher()->getSurname(),
                'created_at' => $deadline->getCreatedAt()->format('d.m.Y H:i'),
                'days_remaining' => $this->calculateDaysRemaining($deadline->getDeadlineDate()),
                'can_submit' => $this->canSubmitDeadline($deadline->getDeadlineDate()),
                'submission_status' => $submission ? 'submitted' : 'not_submitted',
                'submission_data' => $submission ? [
                    'submitted_at' => $submission->getSubmittedAt()->format('d.m.Y H:i'),
                    'file_name' => $submission->getOriginalFileName(),
                    'points' => $submission->getPoints(),
                    'status' => $submission->getStatus(),
                    'teacher_comment' => $submission->getTeacherComment()
                ] : null
            ];
        }

        return $this->json([
            'success' => true,
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber(),
            ],
            'deadlines' => $deadlinesData
        ]);
    }
}
