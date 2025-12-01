<?php

namespace App\Controller;

use App\Entity\DeadlineSubmission;
use App\Entity\Person;
use App\Repository\DeadlineRepository;
use App\Repository\DeadlineSubmissionRepository;
use App\Repository\StudentRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/student')]
class StudentSubmissionController extends AbstractController
{
    private function canSubmitDeadline(\DateTimeInterface $deadlineDate): bool
    {
        $now = new \DateTime();
        return $now <= $deadlineDate;
    }

    #[Route('/deadlines/{deadlineId}/submit', methods: ['POST'])]
    public function submitDeadline(
        int $deadlineId,
        Request $request,
        StudentRepository $studentRepository,
        DeadlineRepository $deadlineRepository,
        DeadlineSubmissionRepository $submissionRepository,
        FileUploadService $fileUploadService,
        EntityManagerInterface $em
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

        $deadline = $deadlineRepository->find($deadlineId);
        if (!$deadline) {
            return $this->json(['error' => 'Deadline topilmadi'], 404);
        }

        if ($deadline->getGroupSubjectTeacher()->getGroup()->getId() !== $student->getGroup()->getId()) {
            return $this->json(['error' => 'Bu deadline sizning guruhingizga tegishli emas'], 403);
        }

        if (!$this->canSubmitDeadline($deadline->getDeadlineDate())) {
            return $this->json([
                'error' => 'Deadline muddati o\'tgan. Topshirish mumkin emas',
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'),
                'current_date' => (new \DateTime())->format('d.m.Y')
            ], 400);
        }

        $existingSubmission = $submissionRepository->findOneByDeadlineAndStudent(
            $deadline->getId(),
            $student->getId()
        );

        if ($existingSubmission) {
            return $this->json([
                'error' => 'Siz allaqachon bu topshiriqni topshirgansiz',
                'submission_date' => $existingSubmission->getSubmittedAt()->format('d.m.Y H:i')
            ], 400);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Fayl yuklanishi shart'], 400);
        }

        try {
            $uploadResult = $fileUploadService->uploadMaterial($file, $student->getId());

            $submission = new DeadlineSubmission();
            $submission->setDeadline($deadline);
            $submission->setStudent($student);
            $submission->setFileName($uploadResult['fileName']);
            $submission->setOriginalFileName($uploadResult['originalName']);
            $submission->setSubmittedAt(new \DateTime());
            $submission->setStatus('submitted');

            $em->persist($submission);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Topshiriq muvaffaqiyatli topshirildi',
                'submission' => [
                    'id' => $submission->getId(),
                    'file_name' => $submission->getOriginalFileName(),
                    'submitted_at' => $submission->getSubmittedAt()->format('d.m.Y H:i'),
                    'status' => $submission->getStatus(),
                    'deadline_title' => $deadline->getTitle(),
                    'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Fayl yuklashda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }
}
