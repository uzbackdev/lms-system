<?php

namespace App\Controller\Admin;

use App\Entity\GroupSubjectTeacher;
use App\Repository\GroupRepository;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\SubjectRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/admin/group-subject-teacher')]
class FirstYearController extends AbstractController
{
    #[Route('/subjects/first-year', methods: ['GET'])]
    public function getFirstYearSubjects(SubjectRepository $subjectRepository): JsonResponse
    {
        $subjects = $subjectRepository->findAllFirstYearSubjects();

        return $this->json([
            'success' => true,
            'data' => array_map(fn($s) => [
                'id' => $s->getId(),
                'name' => $s->getSubjectName(),
                'courseNumber' => $s->getCourseNumber(),
            ], $subjects)
        ]);
    }

    #[Route('/first-year', methods: ['POST'])]
    public function createFirstYear(
        Request $request,
        GroupRepository $groupRepository,
        SubjectRepository $subjectRepository,
        TeacherRepository $teacherRepository,
        GroupSubjectTeacherRepository $gstRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $groupId = $data['group_id'] ?? null;
        $subjectId = $data['subject_id'] ?? null;
        $teacherId = $data['teacher_id'] ?? null;

        if (!$groupId || !$subjectId || !$teacherId) {
            return $this->json(['error' => 'group_id, subject_id va teacher_id kiritilishi kerak'], 400);
        }

        $group = $groupRepository->find($groupId);
        $subject = $subjectRepository->find($subjectId);
        $teacher = $teacherRepository->find($teacherId);

        if (!$group || !$subject || !$teacher) {
            return $this->json(['error' => "Guruh, fan yoki o'qituvchi topilmadi"], 404);
        }

        if ($gstRepository->existsByGroupAndSubject($groupId, $subjectId)) {
            return $this->json(['error' => "Bu guruhga bu fan allaqachon boshqa o'qituvchiga biriktirilgan"], 400);
        }

        if ($group->getCourse()->getNumber() !== 1) {
            return $this->json(['error' => "Bu metod faqat 1-kurslar uchun"], 400);
        }

        if (!$gstRepository->canTeacherTakeMoreSubjects($teacherId)) {
            return $this->json([
                'error' => "Oʻqituvchining dars yuki toʻla"
            ], 400);
        }

        $gst = new GroupSubjectTeacher();
        $gst->setGroup($group);
        $gst->setSubject($subject);
        $gst->setTeacher($teacher);

        $em->persist($gst);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "1-kurs uchun to'g'ri biriktirildi",
            'data' => [
                'id' => $gst->getId(),
                'group' => $group->getGroupNumber(),
                'course' => $group->getCourse()->getNumber(),
                'subject' => $subject->getSubjectName(),
                'teacher' => $teacher->getName() . ' ' . $teacher->getSurname(),
                'department' => $teacher->getFaculty()->getName()
            ]
        ]);
    }
}
