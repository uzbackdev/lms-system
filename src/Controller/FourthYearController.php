<?php

namespace App\Controller;

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

#[Route('/api/group-subject-teacher')]
class FourthYearController extends AbstractController
{
    #[Route('/subjects/fourth-year/by-group/{groupId}', methods: ['GET'])]
    public function getFourthYearSubjectsByGroup(
        int $groupId,
        GroupRepository $groupRepository,
        SubjectRepository $subjectRepository
    ): JsonResponse {
        $group = $groupRepository->find($groupId);

        if (!$group) {
            return $this->json(['error' => 'Guruh topilmadi'], 404);
        }

        $facultyId = $group->getFaculty()->getId();
        $subjects = $subjectRepository->findFourthYearSubjectsByFaculty($facultyId);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($s) => [
                'id' => $s->getId(),
                'name' => $s->getSubjectName(),
                'faculty' => $s->getFaculty()->getName(),
                'courseNumber' => $s->getCourseNumber(),
            ], $subjects)
        ]);
    }

    #[Route('/fourth-year', methods: ['POST'])]
    public function createFourthYear(
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

        if ($group->getCourse()->getNumber() !== 4) {
            return $this->json(['error' => "Bu metod faqat 4-kurslar uchun"], 400);
        }

        if ($teacher->getFaculty()->getId() !== $subject->getFaculty()->getId()) {
            return $this->json(['error' => "Ustoz faqat o'z fakulteti fanini o'qita oladi"], 400);
        }

        if (!$gstRepository->canTeacherTakeMoreSubjects($teacherId)) {
            return $this->json([
                'error' => "Oʻqituvchining dars yuki toʻla (maksimum 10 soat)"
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
            'message' => "4-kurs uchun to'g'ri biriktirildi",
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
