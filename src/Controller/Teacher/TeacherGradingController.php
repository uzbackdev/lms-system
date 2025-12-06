<?php

namespace App\Controller\Teacher;

use App\Repository\DeadlineSubmissionRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\TeacherRepository;
use App\Service\TeacherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teacher')]
class TeacherGradingController extends AbstractController
{
    public function __construct(private TeacherService $teacherService)
    {
    }

    #[Route('/gst/{gstId}/grading-matrix', methods: ['GET'])]
    public function getGSTGradingMatrix(
        int $gstId,
        TeacherRepository $teacherRepository,
        GroupSubjectTeacherRepository $gstRepository
    ): JsonResponse {

        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $gst = $gstRepository->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Guruh/Fan topilmadi yoki ruxsat yoʻq'], 403);
        }

        $matrixData = $this->teacherService->buildGradingMatrix($gst);

        return $this->json([
            'success' => true,
            'gst_info' => $this->teacherService->getGSTInfo($gst),
            'deadlines' => $this->teacherService->prepareDeadlinesList($gst->getDeadlines()),
            'students' => $matrixData
        ]);
    }

    #[Route('/submissions/{submissionId}/grade', methods: ['POST'])]
    public function createGrade(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        return $this->handleGrading($submissionId, $request, $teacherRepository, $submissionRepository, $em, 'POST');
    }

    #[Route('/submissions/{submissionId}/grade', methods: ['PUT'])]
    public function updateGrade(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        return $this->handleGrading($submissionId, $request, $teacherRepository, $submissionRepository, $em, 'PUT');
    }

    private function handleGrading(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em,
        string $method
    ): JsonResponse {
        /** @var \App\Entity\Person $person */
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $points = $data['points'] ?? null;
        $comment = $data['teacher_comment'] ?? null;

        if ($points === null) {
            return $this->json(['error' => 'Ball kiritilmadi'], 400);
        }

        $submission = $submissionRepository->findOneByTeacherAndSubmission($submissionId, $teacher->getId());

        if (!$submission) {
            return $this->json(['error' => 'Topshiriq topilmadi yoki ruxsat yoʻq'], 403);
        }

        $validation = $this->teacherService->validateGrading($submission, $points, $method);
        if (!$validation['is_valid']) {
            return $this->json([
                'error' => implode(', ', $validation['errors']),
                'max_points' => $validation['max_points']
            ], 400);
        }

        $submission->setPoints((int)$points);
        $submission->setTeacherComment($comment);
        $submission->setStatus('graded');
        $submission->setGradedAt(new \DateTime());

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $method === 'POST' ? 'Baho qoʻyildi' : 'Baho yangilandi',
            'submission' => [
                'id' => $submission->getId(),
                'points' => $submission->getPoints(),
                'max_points' => $validation['max_points'],
                'status' => $submission->getStatus(),
                'graded_at' => $submission->getGradedAt()->format('d.m.Y H:i'),
                'grading_method' => $submission->getGradingMethod()
            ]
        ]);
    }
}
