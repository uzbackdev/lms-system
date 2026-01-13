<?php

namespace App\Service;

use App\Entity\Person;
use App\Repository\LessonRepository;
use App\Repository\SemesterRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StudentService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private LessonRepository $lessonRepository,

    ) {}

    public function getCurrentWeekSchedule(int $groupId): array
    {
        $lessons = $this->lessonRepository->findByGroup($groupId);
        $currentWeek = $this->getCurrentWeekDates();

        $weeklySchedule = [];

        foreach ($currentWeek as $dayName => $date) {
            $dayLessons = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null];

            foreach ($lessons as $lesson) {
                if (strtolower($lesson->getDay()->name) === $dayName) {
                    $dayLessons[$lesson->getNumber()->value] = $lesson->getSubject()->getSubjectName();
                }
            }

            $weeklySchedule[$dayName] = [
                'date' => $date,
                'lessons' => $dayLessons
            ];
        }

        return $weeklySchedule;
    }


    private function getCurrentWeekDates(): array
    {
        $monday = new \DateTime('monday this week');

        return [
            'monday'    => $monday->format('Y-m-d'),
            'tuesday'   => (clone $monday)->modify('+1 day')->format('Y-m-d'),
            'wednesday' => (clone $monday)->modify('+2 days')->format('Y-m-d'),
            'thursday'  => (clone $monday)->modify('+3 days')->format('Y-m-d'),
            'friday'    => (clone $monday)->modify('+4 days')->format('Y-m-d')
        ];
    }
}
