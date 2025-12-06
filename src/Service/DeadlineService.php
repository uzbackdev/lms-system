<?php

namespace App\Service;

use App\Entity\Deadline;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Semester;
use App\Repository\DeadlineRepository;
use App\Repository\SemesterRepository;

class DeadlineService
{
    private const MIN_POINTS = 5;
    private const MAX_TOTAL_POINTS = 50;

    public function __construct(
        private DeadlineRepository $deadlineRepository,
        private SemesterRepository $semesterRepository
    ) {}


    public function validateDeadline(GroupSubjectTeacher $gst, \DateTimeInterface $deadlineDate, int $newPoints, ?int $excludeDeadlineId = null): array
    {
        $errors = [];


        $semesterValidation = $this->validateSemesterDate($deadlineDate);
        if (!$semesterValidation['isValid']) {
            $errors[] = $semesterValidation['error'];
        }


        $duplicateValidation = $this->validateDuplicateDeadline($gst, $deadlineDate, $excludeDeadlineId);
        if (!$duplicateValidation['isValid']) {
            $errors[] = $duplicateValidation['error'];
        }


        $pointsValidation = $this->validateDeadlinePoints($gst, $newPoints, $excludeDeadlineId);
        if (!$pointsValidation['isValid']) {
            $errors = array_merge($errors, $pointsValidation['errors']);
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'semesterValidation' => $semesterValidation,
            'duplicateValidation' => $duplicateValidation,
            'pointsValidation' => $pointsValidation
        ];
    }


    private function validateSemesterDate(\DateTimeInterface $date): array
    {
        $activeSemester = $this->semesterRepository->findOneBy(['isActive' => true]);

        if (!$activeSemester) {
            return [
                'isValid' => false,
                'error' => 'Faol semester topilmadi'
            ];
        }

        $dateOnly = $date->format('Y-m-d');
        $startDate = $activeSemester->getStartDate()->format('Y-m-d');
        $endDate = $activeSemester->getEndDate()->format('Y-m-d');

        if ($dateOnly < $startDate || $dateOnly > $endDate) {
            return [
                'isValid' => false,
                'error' => "Sana semester oralig'ida emas. Semester: {$startDate} dan {$endDate} gacha"
            ];
        }

        return [
            'isValid' => true,
            'semester' => $activeSemester
        ];
    }


    private function validateDuplicateDeadline(GroupSubjectTeacher $gst, \DateTimeInterface $date, ?int $excludeDeadlineId = null): array
    {
        $dateOnly = $date->format('Y-m-d');

        $existingDeadline = $this->deadlineRepository->findByGroupSubjectAndDate(
            $gst->getId(),
            $dateOnly,
            $excludeDeadlineId
        );

        if ($existingDeadline) {
            return [
                'isValid' => false,
                'error' => "Bu guruh/fan uchun {$dateOnly} sanasida allaqachon deadline mavjud"
            ];
        }

        return [
            'isValid' => true
        ];
    }
    public function validateDeadlinePoints(GroupSubjectTeacher $gst, int $newPoints, ?int $excludeDeadlineId = null): array
    {
        $currentTotal = $this->deadlineRepository->getTotalPointsForSubject($gst->getId(), $excludeDeadlineId);
        $newTotal = $currentTotal + $newPoints;

        $errors = [];


        if ($newPoints < self::MIN_POINTS) {
            $errors[] = "Minimal ball: " . self::MIN_POINTS;
        }


        if ($newTotal > self::MAX_TOTAL_POINTS) {
            $remaining = self::MAX_TOTAL_POINTS - $currentTotal;
            $errors[] = "Umumiy ball {$newTotal}/" . self::MAX_TOTAL_POINTS .
                ". Qo'shish mumkin bo'lgan maksimal ball: {$remaining}";
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'currentTotal' => $currentTotal,
            'newTotal' => $newTotal,
            'remaining' => self::MAX_TOTAL_POINTS - $currentTotal
        ];
    }

    public function canAddDeadline(GroupSubjectTeacher $gst): bool
    {
        $currentTotal = $this->deadlineRepository->getTotalPointsForSubject($gst->getId());
        return $currentTotal < self::MAX_TOTAL_POINTS;
    }

    public function getPointsInfo(GroupSubjectTeacher $gst): array
    {
        $currentTotal = $this->deadlineRepository->getTotalPointsForSubject($gst->getId());

        return [
            'currentTotal' => $currentTotal,
            'maxTotal' => self::MAX_TOTAL_POINTS,
            'remaining' => self::MAX_TOTAL_POINTS - $currentTotal,
            'minPoints' => self::MIN_POINTS
        ];
    }

}
