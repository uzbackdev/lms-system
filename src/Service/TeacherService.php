<?php

namespace App\Service;

use App\Entity\DeadlineSubmission;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Person;
use App\Repository\DeadlineSubmissionRepository;
use App\Repository\GroupRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\LessonRepository;
use App\Repository\StudentRepository;
use App\Repository\SubjectRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TeacherService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private GroupRepository $groupRepository,
        private SubjectRepository $subjectRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
        private GroupSubjectTeacherRepository $groupSubjectTeacherRepository,
        private LessonRepository $lessonRepository,
        private DeadlineSubmissionRepository $submissionRepository ,
        private EntityManagerInterface $entityManager,
        private LessonPlanService $lessonPlanService
    ) {}

    public function generateLogin(int $teacherId): string
    {
        return 'teacher' . $teacherId;
    }

    public function generatePassword(int $teacherId): string
    {
        return 'password' . $teacherId;
    }

    public function createPersonWithAuth(int $teacherId): array
    {
        $login = $this->generateLogin($teacherId);
        $plainPassword = $this->generatePassword($teacherId);

        $person = new Person();
        $person->setLogin($login);
        $person->setPassword($this->passwordHasher->hashPassword($person, $plainPassword));
        $person->setRoles(['ROLE_TEACHER']);

        return [
            'person' => $person,
            'plain_password' => $plainPassword,
            'login' => $login
        ];
    }

    /**
     * @throws \Exception
     */
    public function getGroupStudents(int $teacherId, int $groupId, int $subjectId): array
    {
        $group = $this->groupRepository->find($groupId);
        $subject = $this->subjectRepository->find($subjectId);
        $teacher = $this->teacherRepository->find($teacherId);

        if (!$group || !$subject || !$teacher) {
            throw new \Exception('Ma\'lumot topilmadi');
        }

        $gst = $this->groupSubjectTeacherRepository->findOneBy([
            'teacher' => $teacher,
            'group' => $group,
            'subject' => $subject
        ]);

        if (!$gst) {
            throw new \Exception('Siz ushbu guruhga bu fandan dars bermaysiz');
        }

        $students = $this->studentRepository->findBy(['group' => $group]);

        return [
            'group' => $group,
            'subject' => $subject,
            'students' => $students
        ];
    }

    public function getTeacherWeeklySchedule(int $teacherId): array
    {
        $lessons = $this->lessonRepository->findByTeacher($teacherId);
        $currentWeek = $this->getCurrentWeekDates();

        $weeklySchedule = [];

        foreach ($currentWeek as $dayName => $date) {
            $dayLessons = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null];

            foreach ($lessons as $lesson) {
                if (strtolower($lesson->getDay()->name) === $dayName) {
                    $dayLessons[$lesson->getNumber()->value] = [
                        'subject' => $lesson->getSubject()->getSubjectName(),
                        'group' => $lesson->getGroup()->getGroupNumber()
                    ];
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
    public function getFileIcon(?string $filename): ?string
    {
        if (!$filename) return null;

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match($extension) {
            'pdf' => '📕',
            'doc', 'docx' => '📄',
            'zip', 'rar', '7z' => '📦',
            'py', 'java', 'cpp', 'js', 'php' => '👨‍💻',
            'sql' => '🗃️',
            'xls', 'xlsx' => '📊',
            'ppt', 'pptx' => '📽️',
            default => '📄'
        };
    }


    public function buildSubmissionData(?DeadlineSubmission $submission, $deadline): array
    {
        $baseData = [
            'deadline_id' => $deadline->getId(),
            'deadline_title' => $deadline->getTitle(),
            'max_points' => $deadline->getMaxPoints(),
            'has_submission' => $submission !== null,
            'file_icon' => $submission ? $this->getFileIcon($submission->getOriginalFileName()) : null,
            'file_name' => $submission ? $submission->getOriginalFileName() : null,
        ];

        if ($submission) {
            $baseData['submission_id'] = $submission->getId();
            $baseData['current_points'] = $submission->getPoints();
            $baseData['status'] = $submission->getStatus();
            $baseData['can_grade'] = $submission->canBeGraded();
            $baseData['grading_method'] = $submission->getGradingMethod();
            $baseData['graded_at'] = $submission->getGradedAt()?->format('d.m.Y H:i');
            $baseData['teacher_comment'] = $submission->getTeacherComment();
        } else {
            $baseData['submission_id'] = null;
            $baseData['current_points'] = null;
            $baseData['status'] = 'not_submitted';
            $baseData['can_grade'] = false;
            $baseData['grading_method'] = null;
            $baseData['graded_at'] = null;
            $baseData['teacher_comment'] = null;
        }

        return $baseData;
    }


    public function buildGradingMatrix(GroupSubjectTeacher $gst): array
    {
        $deadlines = $gst->getDeadlines();
        $group = $gst->getGroup();
        $students = $group->getStudents();

        $matrixData = [];
        foreach ($students as $student) {
            $studentData = [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'surname' => $student->getSurname(),
                'total_points' => 0,
                'total_max_points' => 0,
                'submissions' => []
            ];

            foreach ($deadlines as $deadline) {
                $submission = $this->submissionRepository->findOneByDeadlineAndStudent(
                    $deadline->getId(),
                    $student->getId()
                );

                $submissionData = $this->buildSubmissionData($submission, $deadline);
                $studentData['submissions'][] = $submissionData;

                // Umumiy ballarni hisoblash
                if ($submissionData['current_points'] !== null) {
                    $studentData['total_points'] += $submissionData['current_points'];
                }
                $studentData['total_max_points'] += $deadline->getMaxPoints();
            }

            $matrixData[] = $studentData;
        }

        return $matrixData;
    }


    public function validateGrading(DeadlineSubmission $submission, int $points, string $method): array
    {
        $maxPoints = $submission->getDeadline()->getMaxPoints();
        $errors = [];

        if ($points < 0) {
            $errors[] = 'Ball manfiy boʻlishi mumkin emas';
        }

        if ($points > $maxPoints) {
            $errors[] = "Ball {$maxPoints} dan oshmasligi kerak";
        }

        if ($method === 'POST' && $submission->isGraded()) {
            $errors[] = 'Bu topshiriq allaqachon baholangan. Yangilash uchun PUT metodidan foydalaning.';
        }

        if ($method === 'PUT' && !$submission->isGraded()) {
            $errors[] = 'Bu topshiriq hali baholanmagan. Birinchi baholash uchun POST metodidan foydalaning.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'max_points' => $maxPoints
        ];
    }


    public function getGSTInfo(GroupSubjectTeacher $gst): array
    {
        return [
            'id' => $gst->getId(),
            'group_number' => $gst->getGroup()->getGroupNumber(),
            'subject_name' => $gst->getSubject()->getSubjectName(),
            'teacher_name' => $gst->getTeacher()->getName() . ' ' . $gst->getTeacher()->getSurname()
        ];
    }


    public function prepareDeadlinesList(array $deadlines): array
    {
        return array_map(function($deadline) {
            return [
                'id' => $deadline->getId(),
                'title' => $deadline->getTitle(),
                'max_points' => $deadline->getMaxPoints(),
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y')
            ];
        }, $deadlines);
    }




}
