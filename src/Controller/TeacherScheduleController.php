<?php

namespace App\Controller;

use App\Repository\TeacherRepository;
use App\Service\TeacherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/teacher')]
class TeacherScheduleController extends AbstractController
{
    #[Route('/schedule', methods: ['GET'])]
    public function getMyWeeklySchedule(
        TeacherRepository $teacherRepository,
        TeacherService $teacherService
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $teacherId = $teacher->getId();
        $schedule = $teacherService->getTeacherWeeklySchedule($teacherId);

        return $this->json(['success' => true, 'schedule' => $schedule]);
    }
}
