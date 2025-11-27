<?php

namespace App\Controller;


use App\Entity\Deadline;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Lesson;
use App\Entity\LessonMaterial;
use App\Entity\LessonPlan;
use App\Entity\Teacher;
use App\Repository\DeadlineRepository;
use App\Repository\DeadlineSubmissionRepository;
use App\Repository\FacultyRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\LessonRepository;
use App\Repository\SemesterRepository;
use App\Repository\TeacherRepository;
use App\Service\DeadlineService;
use App\Service\FileUploadService;
use App\Service\TeacherService;
use App\Service\LessonPlanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/teacher')]
class TeacherController extends AbstractController
{

    public function __construct(private TeacherService $teacherService)
    {
    }

    #[Route('/create-teacher', methods: ['POST'])]
    public function createTeacher(
        Request $request,
        FacultyRepository $facultyRepository,
        TeacherService $teacherService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $departmentId = $data['faculty_id'] ?? null;
        $name = $data['name'] ?? null;
        $surname = $data['surname'] ?? null;

        $faculty = $facultyRepository->find($departmentId);
        if (!$faculty) {
            return $this->json(['error' => 'Kafedra topilmadi'], 404);
        }

        $teacher = new Teacher();
        $teacher->setName($name);
        $teacher->setSurname($surname);
        $teacher->setFaculty($faculty);

        $em->persist($teacher);
        $em->flush();

        $authData = $teacherService->createPersonWithAuth($teacher->getId());
        $person = $authData['person'];

        $teacher->setPerson($person);

        $em->persist($person);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "O'qituvchi yaratildi",
            'teacher' => [
                'id' => $teacher->getId(),
                'name' => $teacher->getName(),
                'surname' => $teacher->getSurname(),
                'faculty' => $faculty->getName(),
                'login' => $authData['login'],
                'password' => $authData['plain_password']
            ]
        ]);
    }

    #[Route('/my-groups', methods: ['GET'])]
    public function getMyGroups(
        GroupSubjectTeacherRepository $gstRepository,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $teacherGSTs = $gstRepository->findByTeacher($teacher->getId());

        $uniqueGroups = [];
        foreach ($teacherGSTs as $gst) {
            $groupId = $gst['group_id'];
            if (!isset($uniqueGroups[$groupId])) {
                $uniqueGroups[$groupId] = [
                    'group_id' => $groupId,
                    'group_number' => $gst['groupNumber'],
                    'course_number' => $gst['courseNumber'],
                    'subjects_count' => 0
                ];
            }
            $uniqueGroups[$groupId]['subjects_count']++;
        }

        return $this->json([
            'success' => true,
            'groups' => array_values($uniqueGroups)
        ]);
    }

    #[Route('/my-groups/{groupId}/subjects', methods: ['GET'])]
    public function getGroupSubjects(
        int $groupId,
        GroupSubjectTeacherRepository $gstRepository,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $groupSubjects = $gstRepository->findByTeacherAndGroup($teacher->getId(), $groupId);

        return $this->json([
            'success' => true,
            'subjects' => $groupSubjects
        ]);
    }

    #[Route('/groups/{groupId}/subjects/{subjectId}/students', methods: ['GET'])]
    public function getGroupStudents(
        int $groupId,
        int $subjectId,
        TeacherService $teacherService,
        TeacherRepository $teacherRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        try {
            $data = $teacherService->getGroupStudents($teacher->getId(), $groupId, $subjectId);

            $studentsData = [];
            foreach ($data['students'] as $student) {
                $studentsData[] = [
                    'id' => $student->getId(),
                    'name' => $student->getName(),
                    'surname' => $student->getSurname(),
                    'login' => $student->getPerson()->getLogin(),
                    'group_number' => $data['group']->getGroupNumber(),
                    'course' => $data['group']->getCourse()->getNumber(),
                    'faculty' => $data['group']->getFaculty()->getName()
                ];
            }

            return $this->json([
                'success' => true,
                'group' => [
                    'id' => $data['group']->getId(),
                    'group_number' => $data['group']->getGroupNumber(),
                    'course' => $data['group']->getCourse()->getNumber(),
                    'faculty' => $data['group']->getFaculty()->getName()
                ],
                'subject' => [
                    'id' => $data['subject']->getId(),
                    'name' => $data['subject']->getSubjectName()
                ],
                'students' => $studentsData
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

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

//    #[Route('/lesson-topic', methods: ['POST'])]
//    public function saveLessonTopic(
//        Request $request,
//        EntityManagerInterface $em,
//        LessonRepository $lessonRepository,
//        TeacherRepository $teacherRepository
//    ): JsonResponse {
//        $person = $this->getUser();
//        $teacher = $teacherRepository->findOneBy(['person' => $person]);
//
//        if (!$teacher) {
//            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
//        }
//
//        $data = json_decode($request->getContent(), true);
//
//        $lessonId = $data['lesson_id'] ?? null;
//        $topic = $data['topic'] ?? null;
//
//        if (!$lessonId || !$topic) {
//            return $this->json([
//                'success' => false,
//                'error' => 'lesson_id va topic maydonlari toʻldirilishi shart'
//            ], 400);
//        }
//
//        // Lesson ni tekshirish (o'qituvchiga tegishli ekanligiga)
//        $lesson = $lessonRepository->find($lessonId);
//        if (!$lesson || $lesson->getTeacher()->getId() !== $teacher->getId()) {
//            return $this->json(['error' => 'Dars topilmadi yoki ruxsat yoʻq'], 403);
//        }
//
//        try {
//            $lesson->setTopic($topic);
//            $em->flush();
//
//            return $this->json([
//                'success' => true,
//                'message' => 'Dars mavzusi saqlandi',
//                'lesson' => [
//                    'id' => $lesson->getId(),
//                    'topic' => $lesson->getTopic(),
//                    'day' => $lesson->getDay()->value,
//                    'number' => $lesson->getNumber()->value
//                ]
//            ]);
//
//        } catch (\Exception $e) {
//            return $this->json([
//                'success' => false,
//                'error' => 'Saqlashda xatolik: ' . $e->getMessage()
//            ], 500);
//        }
//    }

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
    #[Route('/deadline', methods: ['POST'])]
    public function createDeadline(
        Request $request,
        EntityManagerInterface $em,
        TeacherRepository $teacherRepository,
        DeadlineService $deadlineService,
        FileUploadService $fileUploadService,
        SemesterRepository $semesterRepository // 🔥 Yangi dependency
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);


        error_log("=== DEBUG DEADLINE ===");
        error_log("Content-Type: " . $request->headers->get('Content-Type'));
        error_log("Method: " . $request->getMethod());


        if (str_contains($request->headers->get('Content-Type'), 'multipart/form-data')) {
            error_log("FormData detected");

            $gstId = $request->request->get('gst_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $maxPoints = $request->request->get('max_points');
            $deadlineDate = $request->request->get('deadline_date');
            $file = $request->files->get('file');

            error_log("gst_id: " . ($gstId ?? 'NULL'));
            error_log("title: " . ($title ?? 'NULL'));
            error_log("description: " . ($description ?? 'NULL'));
            error_log("max_points: " . ($maxPoints ?? 'NULL'));
            error_log("deadline_date: " . ($deadlineDate ?? 'NULL'));
            error_log("Has file: " . ($file ? 'YES' : 'NO'));
        } else {
            error_log("JSON detected");
            $data = json_decode($request->getContent(), true);

            error_log("Raw content: " . $request->getContent());
            error_log("Decoded data: " . print_r($data, true));

            $gstId = $data['gst_id'] ?? null;
            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;
            $maxPoints = $data['max_points'] ?? null;
            $deadlineDate = $data['deadline_date'] ?? null;
            $file = null;
        }

        if (!$gstId || !$title || !$description || !$maxPoints || !$deadlineDate) {
            error_log("MISSING FIELDS DETECTED - Validation failed");

            return $this->json([
                'error' => 'Barcha maydonlar toʻldirilishi shart',
                'debug_info' => [
                    'content_type' => $request->headers->get('Content-Type'),
                    'received_gst_id' => $gstId,
                    'received_title' => $title,
                    'received_description' => $description,
                    'received_max_points' => $maxPoints,
                    'received_deadline_date' => $deadlineDate
                ]
            ], 400);
        }

        error_log("All fields present - Proceeding with validation");

        $gst = $em->getRepository(GroupSubjectTeacher::class)->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            error_log("GST not found or permission denied");
            return $this->json(['error' => 'Guruh/fan topilmadi yoki ruxsat yoʻq'], 403);
        }


        $validation = $deadlineService->validateDeadline($gst, new \DateTime($deadlineDate), (int)$maxPoints);
        if (!$validation['isValid']) {
            error_log("Comprehensive validation failed: " . implode(', ', $validation['errors']));
            return $this->json([
                'success' => false,
                'error' => 'Validatsiya xatolari',
                'details' => $validation['errors'],
                'validation_info' => $validation
            ], 400);
        }

        try {
            $deadline = new Deadline();
            $deadline->setGroupSubjectTeacher($gst);
            $deadline->setSemester($validation['semesterValidation']['semester']); // 🔥 Semester qo'shildi
            $deadline->setTitle($title);
            $deadline->setDescription($description);
            $deadline->setMaxPoints((int)$maxPoints);
            $deadline->setDeadlineDate(new \DateTime($deadlineDate)); // 🔥 Endi faqat sana saqlanadi


            if ($file) {
                error_log("File upload started");
                $uploadResult = $fileUploadService->uploadMaterial($file, $teacher->getId());
                $deadline->setFileName($uploadResult['fileName']);
                $deadline->setOriginalFileName($uploadResult['originalName']);
                error_log("File uploaded: " . $uploadResult['fileName']);
            } else {
                error_log("No file provided");
            }

            $em->persist($deadline);
            $em->flush();

            error_log("Deadline successfully created with ID: " . $deadline->getId());

            return $this->json([
                'success' => true,
                'message' => 'Deadline yaratildi',
                'deadline' => [
                    'id' => $deadline->getId(),
                    'title' => $deadline->getTitle(),
                    'max_points' => $deadline->getMaxPoints(),
                    'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y'), // 🔥 Endi faqat sana
                    'file_name' => $deadline->getOriginalFileName(),
                    'points_info' => $deadlineService->getPointsInfo($gst)
                ]
            ]);

        } catch (\Exception $e) {
            error_log("ERROR creating deadline: " . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => 'Deadline yaratishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

// Guruh bo'yicha deadline larni olish
    #[Route('/groups/{groupId}/deadlines', methods: ['GET'])]
    public function getGroupDeadlines(
        int $groupId,
        TeacherRepository $teacherRepository,
        DeadlineRepository $deadlineRepository
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        $deadlines = $deadlineRepository->findByTeacherAndGroup($teacher->getId(), $groupId);

        $deadlinesData = [];
        foreach ($deadlines as $deadline) {
            $deadlinesData[] = [
                'id' => $deadline->getId(),
                'title' => $deadline->getTitle(),
                'description' => $deadline->getDescription(),
                'max_points' => $deadline->getMaxPoints(),
                'deadline_date' => $deadline->getDeadlineDate()->format('d.m.Y H:i'),
                'file_name' => $deadline->getOriginalFileName(),
                'subject_name' => $deadline->getGroupSubjectTeacher()->getSubject()->getSubjectName(),
                'created_at' => $deadline->getCreatedAt()->format('d.m.Y H:i')
            ];
        }

        return $this->json([
            'success' => true,
            'deadlines' => $deadlinesData
        ]);
    }

// Ballar ma'lumotini olish
    #[Route('/gst/{gstId}/points-info', methods: ['GET'])]
    public function getPointsInfo(
        int $gstId,
        TeacherRepository $teacherRepository,
        DeadlineService $deadlineService,
        EntityManagerInterface $em
    ): JsonResponse {
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        $gst = $em->getRepository(GroupSubjectTeacher::class)->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Ruxsat yoʻq'], 403);
        }

        $pointsInfo = $deadlineService->getPointsInfo($gst);

        return $this->json([
            'success' => true,
            'points_info' => $pointsInfo
        ]);
    }
    #[Route('/gst/{gstId}/grading-matrix', methods: ['GET'])]
    public function getGSTGradingMatrix(
        int $gstId,
        TeacherRepository $teacherRepository,
        GroupSubjectTeacherRepository $gstRepository
    ): JsonResponse {
        /** @var \App\Entity\Person $person */
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $gst = $gstRepository->find($gstId);
        if (!$gst || $gst->getTeacher()->getId() !== $teacher->getId()) {
            return $this->json(['error' => 'Guruh/Fan topilmadi yoki ruxsat yoʻq'], 403);
        }

        $matrixData = $this->teacherService->buildGradingMatrix($gst);

        return $this->json([
            'success' => true,
            'gst_info' => $this->teacherService->getGSTInfo($gst),
            'deadlines' => $this->teacherService->prepareDeadlinesList($gst->getDeadlines()),
            'students' => $matrixData
        ]);
    }

    #[Route('/submissions/{submissionId}/grade', methods: ['POST'])]
    public function createGrade(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        return $this->handleGrading($submissionId, $request, $teacherRepository, $submissionRepository, $em, 'POST');
    }

    #[Route('/submissions/{submissionId}/grade', methods: ['PUT'])]
    public function updateGrade(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        return $this->handleGrading($submissionId, $request, $teacherRepository, $submissionRepository, $em, 'PUT');
    }

    private function handleGrading(
        int $submissionId,
        Request $request,
        TeacherRepository $teacherRepository,
        DeadlineSubmissionRepository $submissionRepository,
        EntityManagerInterface $em,
        string $method
    ): JsonResponse {
        /** @var \App\Entity\Person $person */
        $person = $this->getUser();
        $teacher = $teacherRepository->findOneBy(['person' => $person]);

        if (!$teacher) {
            return $this->json(['error' => 'Oʻqituvchi topilmadi'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $points = $data['points'] ?? null;
        $comment = $data['teacher_comment'] ?? null;

        if ($points === null) {
            return $this->json(['error' => 'Ball kiritilmadi'], 400);
        }

        $submission = $submissionRepository->findOneByTeacherAndSubmission($submissionId, $teacher->getId());

        if (!$submission) {
            return $this->json(['error' => 'Topshiriq topilmadi yoki ruxsat yoʻq'], 403);
        }

        // Service orqali validatsiya
        $validation = $this->teacherService->validateGrading($submission, $points, $method);
        if (!$validation['is_valid']) {
            return $this->json([
                'error' => implode(', ', $validation['errors']),
                'max_points' => $validation['max_points']
            ], 400);
        }

        // Bahoni saqlash
        $submission->setPoints((int)$points);
        $submission->setTeacherComment($comment);
        $submission->setStatus('graded');
        $submission->setGradedAt(new \DateTime());

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $method === 'POST' ? 'Baho qoʻyildi' : 'Baho yangilandi',
            'submission' => [
                'id' => $submission->getId(),
                'points' => $submission->getPoints(),
                'max_points' => $validation['max_points'],
                'status' => $submission->getStatus(),
                'graded_at' => $submission->getGradedAt()->format('d.m.Y H:i'),
                'grading_method' => $submission->getGradingMethod()
            ]
        ]);
    }

}
