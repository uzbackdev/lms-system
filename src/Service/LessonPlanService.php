<?php

namespace App\Service;

use App\Entity\Lesson;
use App\Entity\Semester;
use App\Repository\LessonPlanRepository;
use App\Repository\LessonRepository;
use App\Repository\HolidayRepository;
use App\Repository\SemesterRepository;
use Doctrine\ORM\EntityManagerInterface;

class LessonPlanService
{
    public function __construct(
        private LessonRepository $lessonRepository,
        private HolidayRepository $holidayRepository,
        private SemesterRepository $semesterRepository,
        private LessonPlanRepository $lessonPlanRepository,
        private EntityManagerInterface $em
    ) {}


    public function getGroupSubjectDates(int $groupId, int $subjectId, int $teacherId): array
    {
        $activeSemester = $this->semesterRepository->findActiveSemester();
        if (!$activeSemester) {
            return [];
        }

        $lessons = $this->lessonRepository->findByGroupSubjectAndTeacher($groupId, $subjectId, $teacherId);
        $holidays = $this->holidayRepository->findBy(['semester' => $activeSemester]);

        $allDates = [];

        foreach ($lessons as $lesson) {
            $dates = $this->calculateLessonDates($lesson, $activeSemester, $holidays);

            foreach ($dates as $date) {
                if (!$this->isHoliday($date, $holidays)) {
                    $allDates[] = $date->format('d.m.Y');
                }
            }
        }

        usort($allDates, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        return $allDates;
    }

    public function getLessonPlansSimple(int $groupId, int $subjectId, int $teacherId): array
    {
        $activeSemester = $this->semesterRepository->findActiveSemester();
        if (!$activeSemester) {
            return [];
        }

        $lessons = $this->lessonRepository->findByGroupSubjectAndTeacher($groupId, $subjectId, $teacherId);
        $holidays = $this->holidayRepository->findBy(['semester' => $activeSemester]);

        $lessonPlans = [];

        foreach ($lessons as $lesson) {
            $dates = $this->calculateLessonDates($lesson, $activeSemester, $holidays);

            foreach ($dates as $date) {
                if (!$this->isHoliday($date, $holidays)) {
                    $lessonPlan = $this->lessonPlanRepository->findOneBy([
                        'lesson' => $lesson,
                        'date' => $date
                    ]);

                    $lessonPlans[] = [
                        'date' => $date->format('d.m.Y'),
                        'topic' => $lessonPlan ? $lessonPlan->getTopic() : null,

                    ];
                }
            }
        }

        usort($lessonPlans, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $lessonPlans;
    }
    private function calculateLessonDates(Lesson $lesson, Semester $semester, array $holidays): array
    {
        $dates = [];
        $currentDate = clone $semester->getStartDate();
        $endDate = $semester->getEndDate();
        $targetDayOfWeek = $lesson->getDay()->value;

        while ($currentDate <= $endDate) {
            $currentDayOfWeek = (int)$currentDate->format('N');

            if ($currentDayOfWeek === $targetDayOfWeek) {
                $dates[] = clone $currentDate;
            }

            $currentDate->modify('+1 day');
        }

        return $dates;
    }

    private function isHoliday(\DateTimeInterface $date, array $holidays): bool
    {
        $dateString = $date->format('Y-m-d');
        foreach ($holidays as $holiday) {
            if ($holiday->getDate()->format('Y-m-d') === $dateString) {
                return true;
            }
        }
        return false;
    }
    public function isValidLessonDate(Lesson $lesson, \DateTimeInterface $date): bool
    {
        $activeSemester = $this->semesterRepository->findActiveSemester();
        if (!$activeSemester) {
            return false;
        }

        if ($date < $activeSemester->getStartDate() || $date > $activeSemester->getEndDate()) {
            return false;
        }

        $holidays = $this->holidayRepository->findBy(['semester' => $activeSemester]);
        if ($this->isHoliday($date, $holidays)) {
            return false;
        }

        $targetDayOfWeek = $lesson->getDay()->value;
        $currentDayOfWeek = (int)$date->format('N');

        return $currentDayOfWeek === $targetDayOfWeek;
    }

    public function getValidLessonDates(Lesson $lesson): array
    {
        $activeSemester = $this->semesterRepository->findActiveSemester();
        if (!$activeSemester) {
            return [];
        }

        $holidays = $this->holidayRepository->findBy(['semester' => $activeSemester]);
        $dates = $this->calculateLessonDates($lesson, $activeSemester, $holidays);

        $validDates = [];
        foreach ($dates as $date) {
            if (!$this->isHoliday($date, $holidays)) {
                $validDates[] = $date->format('d.m.Y');
            }
        }

        return $validDates;
    }
}
