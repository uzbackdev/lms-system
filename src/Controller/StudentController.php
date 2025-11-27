<?php

namespace App\Controller;

use App\Entity\DeadlineSubmission;
use App\Entity\Person;
use App\Entity\Student;
use App\Repository\DeadlineRepository;
use App\Repository\DeadlineSubmissionRepository;
use App\Repository\GroupRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\LessonRepository;
use App\Repository\StudentRepository;
use App\Service\FileUploadService;
use App\Service\StudentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/student')]
class StudentController extends AbstractController
{
    #[Route('/change-password', name: 'api_student_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {

        /** @var Person $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }


        $data = json_decode($request->getContent(), true);

        $oldPassword = $data['oldPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';



        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            return $this->json(['error' => 'Eski parol notoʻgʻri'], 400);
        }


        if ($newPassword !== $confirmPassword) {
            return $this->json(['error' => 'Yangi parol va tasdiqlash paroli mos kemadi'], 400);
        }


        if ($passwordHasher->isPasswordValid($user, $newPassword)) {
            return $this->json(['error' => 'Bu parol eski paroldan farq qilishi kerak'], 400);
        }


        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();


        return $this->json([
            'success' => true,
            'message' => 'Parol yangilandi'
        ]);
    }
    #[Route('/create-student', methods: ['POST'])]
    public function createStudent(
        Request $request,
        GroupRepository $groupRepository,
        StudentService $studentAuthService,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);


        $groupId = $data['group_id'] ?? null;
        $name = $data['name'] ?? null;
        $surname = $data['surname'] ?? null;
        $address = $data['address'] ?? null;



        $group = $groupRepository->find($groupId);
        if (!$group) {
            return $this->json("Guruh topilmadi", 404);
        }


        $student = new Student();
        $student->setName($name);
        $student->setSurname($surname);
        $student->setAddress($address);
        $student->setGroup($group);

        $em->persist($student);
        $em->flush();


        $authData = $studentAuthService->createPersonWithAuth($student->getId());
        $person = $authData['person'];


        $student->setPerson($person);


        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Student yaratildi',
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroupNumber(),
                'course' => $student->getCourseNumber(),
                'login' => $authData['login'],
                'password' => $authData['plain_password']
            ]
        ]);
    }

    #[Route('/schedule', methods: ['GET'])]
    public function getMyCurrentWeekSchedule(
        StudentRepository $studentRepository,
        StudentService $studentService
    ): JsonResponse {
        $person = $this->getUser();
        $student = $studentRepository->findOneBy(['person' => $person]);

        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        $groupId = $student->getGroup()->getId();
        $schedule = $studentService->getCurrentWeekSchedule($groupId);

        return $this->json(['success' => true, 'schedule' => $schedule]);
    }
    #[Route('/my-subjects-teachers', methods: ['GET'])]
    public function getMySubjectsAndTeachers(
        StudentRepository $studentRepository,
        GroupSubjectTeacherRepository $gstRepository
    ): JsonResponse {
        /** @var Person $person */
        $person = $this->getUser();

        if (!$person) {
            return $this->json(['error' => 'Authentication required'], 401);
        }


        $student = $studentRepository->findOneBy(['person' => $person]);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        // Talaba guruhini olish
        $groupId = $student->getGroup()->getId();

        // Guruhga tegishli barcha fan-ustoz juftliklarini olish
        $subjectsTeachers = $gstRepository->findByGroup($groupId);


        $data = [];
        foreach ($subjectsTeachers as $gst) {
            $data[] = [
                'id' => $gst->getId(),
                'subject' => [
                    'id' => $gst->getSubject()->getId(),
                    'name' => $gst->getSubject()->getSubjectName(),
                    'course_number' => $gst->getSubject()->getCourseNumber(),
                ],
                'teacher' => [
                    'id' => $gst->getTeacher()->getId(),
                    'name' => $gst->getTeacher()->getName(),
                    'surname' => $gst->getTeacher()->getSurname(),
                    'faculty' => $gst->getTeacher()->getFaculty()->getName(),
                ],
                'group' => [
                    'id' => $gst->getGroup()->getId(),
                    'number' => $gst->getGroup()->getGroupNumber(),
                    'course' => $gst->getGroup()->getCourse()->getNumber(),
                ]
            ];
        }

        return $this->json([
            'success' => true,
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber(),
                'course' => $student->getGroup()->getCourse()->getNumber(),
            ],
            'subjects_teachers' => $data
        ]);
    }
    #[Route('/deadlines', methods: ['GET'])]
    public function getMyDeadlines(
        StudentRepository $studentRepository,
        DeadlineRepository $deadlineRepository,
        DeadlineSubmissionRepository $submissionRepository
    ): JsonResponse {
        /** @var Person $person */
        $person = $this->getUser();

        if (!$person) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $student = $studentRepository->findOneBy(['person' => $person]);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        // Talaba guruhini olish
        $groupId = $student->getGroup()->getId();

        // Guruh bo'yicha deadlinelarni olish
        $deadlines = $deadlineRepository->findByGroup($groupId);

        $deadlinesData = [];
        foreach ($deadlines as $deadline) {
            // Talaba bu deadline ni topshirganligini tekshirish
            $submission = $submissionRepository->findOneByDeadlineAndStudent(
                $deadline->getId(),
                $student->getId()
            );

            $deadlinesData[] = [
                'id' => $deadline->getId(),
                'title' => $deadline->getTitle(),
                'description' => $deadline->getDescription(),
                'max_points' => $deadline->getMaxPoints(),
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'),
                'file_name' => $deadline->getOriginalFileName(),
                'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName(),
                'teacher_name' => $deadline->getGroupSubjectTeacher()->getTeacher()->getName() . ' ' .
                    $deadline->getGroupSubjectTeacher()->getTeacher()->getSurname(),
                'created_at' => $deadline->getCreatedAt()->format('d.m.Y H:i'),
                'days_remaining' => $this->calculateDaysRemaining($deadline->getDeadlineDate()),
                'can_submit' => $this->canSubmitDeadline($deadline->getDeadlineDate()),
                'submission_status' => $submission ? 'submitted' : 'not_submitted',
                'submission_data' => $submission ? [
                    'submitted_at' => $submission->getSubmittedAt()->format('d.m.Y H:i'),
                    'file_name' => $submission->getOriginalFileName(),
                    'points' => $submission->getPoints(),
                    'status' => $submission->getStatus(),
                    'teacher_comment' => $submission->getTeacherComment()
                ] : null
            ];
        }

        return $this->json([
            'success' => true,
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'group' => $student->getGroup()->getGroupNumber(),
            ],
            'deadlines' => $deadlinesData
        ]);
    }

    #[Route('/deadlines/{deadlineId}/submit', methods: ['POST'])]
    public function submitDeadline(
        int $deadlineId,
        Request $request,
        StudentRepository $studentRepository,
        DeadlineRepository $deadlineRepository,
        DeadlineSubmissionRepository $submissionRepository,
        FileUploadService $fileUploadService,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var Person $person */
        $person = $this->getUser();

        if (!$person) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $student = $studentRepository->findOneBy(['person' => $person]);
        if (!$student) {
            return $this->json(['error' => 'Student topilmadi'], 404);
        }

        // Deadline ni tekshirish
        $deadline = $deadlineRepository->find($deadlineId);
        if (!$deadline) {
            return $this->json(['error' => 'Deadline topilmadi'], 404);
        }

        // Talaba o'sha guruhda ekanligini tekshirish
        if ($deadline->getGroupSubjectTeacher()->getGroup()->getId() !== $student->getGroup()->getId()) {
            return $this->json(['error' => 'Bu deadline sizning guruhingizga tegishli emas'], 403);
        }

        // Deadline muddati o'tmaganligini tekshirish
        if (!$this->canSubmitDeadline($deadline->getDeadlineDate())) {
            return $this->json([
                'error' => 'Deadline muddati o\'tgan. Topshirish mumkin emas',
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'),
                'current_date' => (new \DateTime())->format('d.m.Y')
            ], 400);
        }

        // Oldin topshirilgan topshiriq borligini tekshirish
        $existingSubmission = $submissionRepository->findOneByDeadlineAndStudent(
            $deadline->getId(),
            $student->getId()
        );

        if ($existingSubmission) {
            return $this->json([
                'error' => 'Siz allaqachon bu topshiriqni topshirgansiz',
                'submission_date' => $existingSubmission->getSubmittedAt()->format('d.m.Y H:i')
            ], 400);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'Fayl yuklanishi shart'], 400);
        }

        try {
            // Faylni yuklash
            $uploadResult = $fileUploadService->uploadMaterial($file, $student->getId());

            // Topshiriqni yaratish
            $submission = new DeadlineSubmission();
            $submission->setDeadline($deadline);
            $submission->setStudent($student);
            $submission->setFileName($uploadResult['fileName']);
            $submission->setOriginalFileName($uploadResult['originalName']);
            $submission->setSubmittedAt(new \DateTime());
            $submission->setStatus('submitted');

            $em->persist($submission);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Topshiriq muvaffaqiyatli topshirildi',
                'submission' => [
                    'id' => $submission->getId(),
                    'file_name' => $submission->getOriginalFileName(),
                    'submitted_at' => $submission->getSubmittedAt()->format('d.m.Y H:i'),
                    'status' => $submission->getStatus(),
                    'deadline_title' => $deadline->getTitle(),
                    'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Fayl yuklashda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    private function calculateDaysRemaining(\DateTimeInterface $deadlineDate): int
    {
        $now = new \DateTime();
        $interval = $now->diff($deadlineDate);
        return (int)$interval->format('%r%a');
    }

    private function canSubmitDeadline(\DateTimeInterface $deadlineDate): bool
    {
        $now = new \DateTime();
        return $now <= $deadlineDate;
    }
}
