<?php

namespace App\Controller;

use App\Entity\Holiday;
use App\Entity\Lesson;

use App\Entity\Semester;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\HolidayRepository;
use App\Repository\LessonRepository;
use App\Repository\SemesterRepository;
use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
#[Route('general-admin')]
class AdminController extends AbstractController
{
    #[Route('/admin/teachers/password', methods: ['PUT'])]
    public function changeTeacherPassword(
        Request $request,
        TeacherRepository $teacherRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $teacherId = $data['teacher_id'] ?? null;
        $newPassword = $data['new_password'] ?? null;


        if (!$teacherId) {
            return $this->json(['error' => 'Teacher ID kiritilmagan'], 400);
        }


        $teacher = $teacherRepository->find($teacherId);
        if (!$teacher) {
            return $this->json(['error' => 'O\'qituvchi topilmadi'], 404);
        }


        if (!$newPassword) {
            return $this->json(['error' => 'Yangi parol kiritilmagan'], 400);
        }

        $passwordConstraint = new Assert\Length([
            'min' => 6,
            'minMessage' => 'Parol kamida 6 ta belgidan iborat bo\'lishi kerak'
        ]);

        $errors = $validator->validate($newPassword, $passwordConstraint);
        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 400);
        }

        $person = $teacher->getPerson();
        $hashedPassword = $passwordHasher->hashPassword($person, $newPassword);
        $person->setPassword($hashedPassword);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Parol muvaffaqiyatli yangilandi',
            'teacher' => [
                'id' => $teacher->getId(),
                'name' => $teacher->getName(),
                'surname' => $teacher->getSurname()
            ]
        ]);
    }
    #[Route('/admin/students/password', methods: ['PUT'])]
    public function changeStudentPassword(
        Request $request,
        StudentRepository $studentRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $studentId = $data['student_id'] ?? null;
        $newPassword = $data['new_password'] ?? null;


        if (!$studentId) {
            return $this->json(['error' => 'Student ID kiritilmagan'], 400);
        }


        $student = $studentRepository->find($studentId);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }


        if (!$newPassword) {
            return $this->json(['error' => 'Yangi parol kiritilmagan'], 400);
        }

        $passwordConstraint = new Assert\Length([
            'min' => 6,
            'minMessage' => 'Parol kamida 6 ta belgidan iborat bo\'lishi kerak'
        ]);

        $errors = $validator->validate($newPassword, $passwordConstraint);
        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 400);
        }


        $person = $student->getPerson();
        $hashedPassword = $passwordHasher->hashPassword($person, $newPassword);
        $person->setPassword($hashedPassword);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Student paroli muvaffaqiyatli yangilandi',
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber()
            ]
        ]);
    }
    #[Route('/api/lesson-schedule', methods: ['POST'])]
    public function createLessonSchedule(
        Request $request,
        LessonRepository $lessonRepository,
        GroupSubjectTeacherRepository $gstRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $gstId = $data['group_subject_teacher_id'] ?? null;
        $day = WeekDay::tryFrom($data['day'] ?? '');
        $number = LessonNumber::tryFrom($data['number'] ?? 1);


        if (!$gstId || !$day || !$number) {
            return $this->json(['error' => "Ma'lumotlar to'liqmas"], 400);
        }

        $gst = $gstRepository->find($gstId);
        if (!$gst) {
            return $this->json(['error' => 'Guruh-Fan-Ustoz topilmadi'], 404);
        }


        if ($lessonRepository->isTeacherBusy($gst->getTeacher()->getId(), $day, $number)) {
            return $this->json(['error' => 'Ustoz bu vaqtda band'], 400);
        }


        if ($lessonRepository->isGroupBusy($gst->getGroup()->getId(), $day, $number)) {
            return $this->json(['error' => 'Guruh bu vaqtda band'], 400);
        }


        $weeklySubjectCount = $lessonRepository->getWeeklySubjectCount($gstId);
        if ($weeklySubjectCount >= 2) {
            return $this->json(['error' => 'Bu fan haftada 2 martadan ko‘p bo‘lishi mumkinmas'], 400);
        }


        $dailyLessons = $lessonRepository->getDailyGroupLessons($gst->getGroup()->getId(), $day);
        if ($dailyLessons >= 5) {
            return $this->json(['error' => '1 kunda 5 ta darsdan oshmasligimiza kerek'], 400);
        }

        $lesson = new Lesson();
        $lesson->setGroupSubjectTeacher($gst);
        $lesson->setDay($day);
        $lesson->setNumber($number);

        $entityManager->persist($lesson);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'lesson' => [
                'id' => $lesson->getId(),
                'group' => $gst->getGroup()->getGroupNumber(),
                'subject' => $gst->getSubject()->getSubjectName(),
                'teacher' => $gst->getTeacher()->getName() . ' ' . $gst->getTeacher()->getSurname(),
                'day' => $day->name,
                'number' => $number->value,
                'weekly_count' => $weeklySubjectCount + 1
            ]
        ]);
    }
    #[Route('/holidays' , methods: ['GET'])]
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
    #[Route('create-holiday' , methods: ['POST'])]
    public function createHoliday(
        Request $request,
        SemesterRepository $semesterRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $date = \DateTime::createFromFormat('d.m.Y', $data['date']);
        if (!$date) {
            return $this->json(['error' => 'Notoʻgʻri sana format'], 400);
        }

        $holiday = new Holiday();
        $holiday->setDate($date);
        $holiday->setName($data['name']);

        $em->persist($holiday);
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Bayram qoʻshildi']);
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
            // Agar yangi semestr faol bo'lsa, boshqa semestrlarni faol emas qilamiz
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
