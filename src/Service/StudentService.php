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

    public function generateLogin(int $studentId): string {
        return 'student' . $studentId;
    }

    public function generatePassword(int $studentId): string {
        return 'password' . $studentId;
    }

    public function createPersonWithAuth(int $studentId): array
    {
        $login = $this->generateLogin($studentId);
        $plainPassword = $this->generatePassword($studentId);

        $person = new Person();
        $person->setLogin($login);
        $person->setPassword($this->passwordHasher->hashPassword($person, $plainPassword));
        $person->setRoles(['ROLE_STUDENT']);

        return [
            'person' => $person,
            'plain_password' => $plainPassword,
            'login' => $login
        ];
    }

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
        $today = new \DateTime();
        $monday = clone $today;
        $monday->modify('monday this week');

        return [
            'monday' => $monday->format('Y-m-d'),
            'tuesday' => $monday->modify('+1 day')->format('Y-m-d'),
            'wednesday' => $monday->modify('+1 day')->format('Y-m-d'),
            'thursday' => $monday->modify('+1 day')->format('Y-m-d'),
            'friday' => $monday->modify('+1 day')->format('Y-m-d')
        ];
    }
}
