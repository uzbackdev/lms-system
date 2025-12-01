<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\LessonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/general-admin')]
class ScheduleController extends AbstractController
{
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
}
