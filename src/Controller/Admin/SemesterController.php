<?php

namespace App\Controller\Admin;

use App\Entity\Holiday;
use App\Entity\Semester;
use App\Repository\HolidayRepository;
use App\Repository\SemesterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class SemesterController extends AbstractController
{
    #[Route('/holidays', methods: ['GET'])]
    public function getHolidays(HolidayRepository $holidayRepository): JsonResponse
    {
        $holidays = $holidayRepository->findAll();

        $result = [];
        foreach ($holidays as $holiday) {
            $result[] = [
                'id' => $holiday->getId(),
                'date' => $holiday->getDate()->format('d.m.Y'),
                'name' => $holiday->getName(),
                'semester' => $holiday->getSemester()->getName()
            ];
        }

        return $this->json($result);
    }

    #[Route('/create-holiday', methods: ['POST'])]
    public function createHoliday(
        Request $request,
        SemesterRepository $semesterRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $date = \DateTime::createFromFormat('d.m.Y', $data['date'] ?? '');
        if (!$date) {
            return $this->json(['error' => 'Notoʻgʻri sana format'], 400);
        }

        $name = $data['name'] ?? null;
        $semesterId = $data['semester_id'] ?? null;

        if (!$name) {
            return $this->json(['error' => 'Bayram nomi kiritilmagan'], 400);
        }

        if (!$semesterId) {
            return $this->json(['error' => 'Semester ID kiritilmagan'], 400);
        }

        $semester = $semesterRepository->find($semesterId);
        if (!$semester) {
            return $this->json(['error' => 'Semester topilmadi'], 404);
        }

        $holiday = new Holiday();
        $holiday->setDate($date);
        $holiday->setName($name);
        $holiday->setSemester($semester);

        $em->persist($holiday);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Bayram qoʻshildi',
            'holiday' => [
                'id' => $holiday->getId(),
                'name' => $holiday->getName(),
                'date' => $holiday->getDate()->format('d.m.Y'),
                'semester' => $holiday->getSemester()->getName()
            ]
        ]);
    }

    #[Route('/create-semester', methods: ['POST'])]
    public function createSemester(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $isActive = $data['is_active'] ?? false;

        if (!$name || !$startDate || !$endDate) {
            return $this->json([
                'success' => false,
                'error' => 'Name, start_date va end_date maydonlari to\'ldirilishi shart'
            ], 400);
        }

        try {
            if ($isActive) {
                $allSemesters = $em->getRepository(Semester::class)->findAll();
                foreach ($allSemesters as $semester) {
                    $semester->setIsActive(false);
                }
            }

            $semester = new Semester();
            $semester->setName($name);
            $semester->setStartDate(new \DateTime($startDate));
            $semester->setEndDate(new \DateTime($endDate));
            $semester->setIsActive($isActive);

            $em->persist($semester);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Semestr muvaffaqiyatli yaratildi',
                'semester' => [
                    'id' => $semester->getId(),
                    'name' => $semester->getName(),
                    'start_date' => $semester->getStartDate()->format('Y-m-d'),
                    'end_date' => $semester->getEndDate()->format('Y-m-d'),
                    'is_active' => $semester->isActive()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Semestr yaratishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/semesters', methods: ['GET'])]
    public function getSemesters(EntityManagerInterface $em): JsonResponse
    {
        $semesters = $em->getRepository(Semester::class)->findAll();

        $semestersData = [];
        foreach ($semesters as $semester) {
            $semestersData[] = [
                'id' => $semester->getId(),
                'name' => $semester->getName(),
                'start_date' => $semester->getStartDate()->format('Y-m-d'),
                'end_date' => $semester->getEndDate()->format('Y-m-d'),
                'is_active' => $semester->isActive()
            ];
        }

        return $this->json([
            'success' => true,
            'semesters' => $semestersData
        ]);
    }

    #[Route('/semester/{id}/activate', methods: ['POST'])]
    public function activateSemester(int $id, EntityManagerInterface $em): JsonResponse
    {
        $semester = $em->getRepository(Semester::class)->find($id);

        if (!$semester) {
            return $this->json([
                'success' => false,
                'error' => 'Semestr topilmadi'
            ], 404);
        }

        $allSemesters = $em->getRepository(Semester::class)->findAll();
        foreach ($allSemesters as $otherSemester) {
            $otherSemester->setIsActive(false);
        }

        $semester->setIsActive(true);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Semestr faol holatga o\'tkazildi',
            'semester' => [
                'id' => $semester->getId(),
                'name' => $semester->getName(),
                'is_active' => $semester->isActive()
            ]
        ]);
    }
}
