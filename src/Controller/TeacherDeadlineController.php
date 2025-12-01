<?php

namespace App\Controller;

use App\Entity\Deadline;
use App\Entity\GroupSubjectTeacher;
use App\Repository\DeadlineRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\TeacherRepository;
use App\Service\DeadlineService;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/teacher')]
class TeacherDeadlineController extends AbstractController
{
    public function __construct(private TeacherService $teacherService)
    {
    }

    #[Route('/deadline', methods: ['POST'])]
    public function createDeadline(
        Request $request,
        EntityManagerInterface $em,
        TeacherRepository $teacherRepository,
        DeadlineService $deadlineService,
        FileUploadService $fileUploadService
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (str_contains($request->headers->get('Content-Type'), 'multipart/form-data')) {
            $gstId = $request->request->get('gst_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $maxPoints = $request->request->get('max_points');
            $deadlineDate = $request->request->get('deadline_date');
            $file = $request->files->get('file');
        } else {
            $data = json_decode($request->getContent(), true);
            $gstId = $data['gst_id'] ?? null;
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;
            $maxPoints = $data['max_points'] ?? null;
            $deadlineDate = $data['deadline_date'] ?? null;
            $file = null;
        }

        if (!$gstId || !$title || !$description || !$maxPoints || !$deadlineDate) {
            return $this->json([
                'error' => 'Barcha maydonlar toʻldirilishi shart',
                'debug_info' => [
                    'content_type' => $request->headers->get('Content-Type'),
                    'received_gst_id' => $gstId,
                    'received_title' => $title,
                    'received_description' => $description,
                    'received_max_points' => $maxPoints,
                    'received_deadline_date' => $deadlineDate
                ]
            ], 400);
        }

        $gst = $em->getRepository(GroupSubjectTeacher::class)->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Guruh/fan topilmadi yoki ruxsat yoʻq'], 403);
        }

        $validation = $deadlineService->validateDeadline($gst, new \DateTime($deadlineDate), (int)$maxPoints);
        if (!$validation['isValid']) {
            return $this->json([
                'success' => false,
                'error' => 'Validatsiya xatolari',
                'details' => $validation['errors'],
                'validation_info' => $validation
            ], 400);
        }

        try {
            $deadline = new Deadline();
            $deadline->setGroupSubjectTeacher($gst);
            $deadline->setSemester($validation['semesterValidation']['semester']);
            $deadline->setTitle($title);
            $deadline->setDescription($description);
            $deadline->setMaxPoints((int)$maxPoints);
            $deadline->setDeadlineDate(new \DateTime($deadlineDate));

            if ($file) {
                $uploadResult = $fileUploadService->uploadMaterial($file, $teacher->getId());
                $deadline->setFileName($uploadResult['fileName']);
                $deadline->setOriginalFileName($uploadResult['originalName']);
            }

            $em->persist($deadline);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Deadline yaratildi',
                'deadline' => [
                    'id' => $deadline->getId(),
                    'title' => $deadline->getTitle(),
                    'max_points' => $deadline->getMaxPoints(),
                    'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'),
                    'file_name' => $deadline->getOriginalFileName(),
                    'points_info' => $deadlineService->getPointsInfo($gst)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Deadline yaratishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/groups/{groupId}/deadlines', methods: ['GET'])]
    public function getGroupDeadlines(
        int $groupId,
        TeacherRepository $teacherRepository,
        DeadlineRepository $deadlineRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        $deadlines = $deadlineRepository->findByTeacherAndGroup($teacher->getId(), $groupId);

        $deadlinesData = [];
        foreach ($deadlines as $deadline) {
            $deadlinesData[] = [
                'id' => $deadline->getId(),
                'title' => $deadline->getTitle(),
                'description' => $deadline->getDescription(),
                'max_points' => $deadline->getMaxPoints(),
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y H:i'),
                'file_name' => $deadline->getOriginalFileName(),
                'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName(),
                'created_at' => $deadline->getCreatedAt()->format('d.m.Y H:i')
            ];
        }

        return $this->json([
            'success' => true,
            'deadlines' => $deadlinesData
        ]);
    }

    #[Route('/gst/{gstId}/points-info', methods: ['GET'])]
    public function getPointsInfo(
        int $gstId,
        TeacherRepository $teacherRepository,
        DeadlineService $deadlineService,
        EntityManagerInterface $em
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        $gst = $em->getRepository(GroupSubjectTeacher::class)->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Ruxsat yoʻq'], 403);
        }

        $pointsInfo = $deadlineService->getPointsInfo($gst);

        return $this->json([
            'success' => true,
            'points_info' => $pointsInfo
        ]);
    }
}
