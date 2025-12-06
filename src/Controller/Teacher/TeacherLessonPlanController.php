<?php

namespace App\Controller\Teacher;

use App\Entity\Lesson;
use App\Entity\LessonMaterial;
use App\Entity\LessonPlan;
use App\Repository\TeacherRepository;
use App\Service\FileUploadService;
use App\Service\LessonPlanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teacher')]
class TeacherLessonPlanController extends AbstractController
{
    #[Route('/schedules/{groupId}/{subjectId}', methods: ['GET'])]
    public function getSchedules(
        int $groupId,
        int $subjectId,
        LessonPlanService $lessonPlanService,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Kirish kerak'], 401);
        }

        $dates = $lessonPlanService->getGroupSubjectDates($groupId, $subjectId, $teacher->getId());

        if (empty($dates)) {
            return $this->json(['error' => 'Dars sanalari topilmadi'], 404);
        }

        return $this->json([
            'group_id' => $groupId,
            'subject_id' => $subjectId,
            'teacher_name' => $teacher->getName() . ' ' . $teacher->getSurname(),
            'dates' => $dates
        ]);
    }

    #[Route('/lesson-plans/{groupId}/{subjectId}', methods: ['GET'])]
    public function getLessonPlans(
        int $groupId,
        int $subjectId,
        LessonPlanService $lessonPlanService,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $lessonPlans = $lessonPlanService->getLessonPlansSimple(
            $groupId,
            $subjectId,
            $teacher->getId()
        );

        return $this->json($lessonPlans);
    }

    #[Route('/lesson-plan', methods: ['POST'])]
    public function saveLessonPlan(
        Request $request,
        EntityManagerInterface $em,
        TeacherRepository $teacherRepository,
        LessonPlanService $lessonPlanService
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $lessonId = $data['lesson_id'] ?? null;
        $date = $data['date'] ?? null;
        $topic = $data['topic'] ?? null;

        if (!$lessonId || !$date || !$topic) {
            return $this->json([
                'success' => false,
                'error' => 'lesson_id, date va topic maydonlari toʻldirilishi shart'
            ], 400);
        }

        $lesson = $em->getRepository(Lesson::class)->find($lessonId);
        if (!$lesson || $lesson->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Dars topilmadi yoki ruxsat yoʻq'], 403);
        }

        try {
            $dateObj = \DateTime::createFromFormat('d.m.Y', $date);

            $isValidDate = $lessonPlanService->isValidLessonDate($lesson, $dateObj);
            if (!$isValidDate) {
                return $this->json([
                    'success' => false,
                    'error' => 'Bu sana ushbu dars uchun rejalashtirilmagan'
                ], 400);
            }

            $lessonPlan = $em->getRepository(LessonPlan::class)->findOneBy([
                'lesson' => $lesson,
                'date' => $dateObj
            ]);

            if (!$lessonPlan) {
                $lessonPlan = new LessonPlan();
                $lessonPlan->setLesson($lesson);
                $lessonPlan->setDate($dateObj);
            }

            $lessonPlan->setTopic($topic);
            $em->persist($lessonPlan);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Dars rejasi saqlandi',
                'lesson_plan' => [
                    'id' => $lessonPlan->getId(),
                    'date' => $lessonPlan->getDate()->format('d.m.Y'),
                    'topic' => $lessonPlan->getTopic()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Saqlashda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/lesson-plan/{id}/materials', methods: ['POST'])]
    public function uploadMaterials(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        FileUploadService $fileUploadService,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $lessonPlan = $em->getRepository(LessonPlan::class)->find($id);
        $uploadedFiles = $request->files->get('materials');

        if (!$uploadedFiles) {
            return $this->json(['error' => 'Fayl tanlanmadi'], 400);
        }

        $materials = [];

        foreach ($uploadedFiles as $file) {
            try {
                $uploadResult = $fileUploadService->uploadMaterial($file, $teacher->getId());

                $material = new LessonMaterial();
                $material->setFileName($uploadResult['fileName']);
                $material->setOriginalName($uploadResult['originalName']);
                $material->setFileType($uploadResult['fileType']);

                $lessonPlan->addMaterial($material);
                $em->persist($material);

                $materials[] = [
                    'id' => $material->getId(),
                    'file_name' => $material->getFileName(),
                    'original_name' => $material->getOriginalName(),
                    'file_type' => $material->getFileType(),
                    'download_url' => "/uploads/{$material->getFileName()}"
                ];

            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => $e->getMessage()
                ], 400);
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => count($materials) . ' ta fayl yuklandi',
            'materials' => $materials
        ]);
    }

    #[Route('/lesson-plan/{id}/materials', methods: ['GET'])]
    public function getMaterials(int $id, EntityManagerInterface $em): JsonResponse
    {
        $lessonPlan = $em->getRepository(LessonPlan::class)->find($id);

        $materials = [];
        foreach ($lessonPlan->getMaterials() as $material) {
            $materials[] = [
                'id' => $material->getId(),
                'original_name' => $material->getOriginalName(),
                'file_type' => $material->getFileType(),
                'download_url' => "/uploads/{$material->getFileName()}"
            ];
        }

        return $this->json([
            'success' => true,
            'materials' => $materials
        ]);
    }

    #[Route('/material/{id}', methods: ['DELETE'])]
    public function deleteMaterial(
        int $id,
        EntityManagerInterface $em,
        FileUploadService $fileUploadService
    ): JsonResponse {
        $material = $em->getRepository(LessonMaterial::class)->find($id);

        if (!$material) {
            return $this->json(['error' => 'Material topilmadi'], 404);
        }

        try {
            $fileUploadService->deleteFile($material->getFileName());
            $em->remove($material);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Fayl o\'chirildi'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Fayl o\'chirishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }
}
