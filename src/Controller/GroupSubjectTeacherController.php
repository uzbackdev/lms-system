<?php

namespace App\Controller;

use App\Entity\GroupSubjectTeacher;
use App\Repository\GroupSubjectTeacherRepository;
use App\Repository\GroupRepository;
use App\Repository\SubjectRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/group-subject-teacher')]
class GroupSubjectTeacherController extends AbstractController
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


    #[Route('/teachers/by-subject/{subjectId}', methods: ['GET'])]
    public function getTeachersBySubject(int $subjectId, TeacherRepository $teacherRepository, SubjectRepository $subjectRepository): JsonResponse
    {
        $subject = $subjectRepository->find($subjectId);
        if (!$subject) {
            return $this->json(['error' => 'Fan topilmadi'], 404);
        }


        $teachers = $teacherRepository->findTeachersBySubjectWithCommonCheck($subject);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($t) => [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'surname' => $t->getSurname(),
                'faculty' => $t->getFaculty()->getName(),
            ], $teachers)
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
            'message' => "1-kurs uchun to‘g‘ri biriktirildi",
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

    #[Route('/second-year', methods: ['POST'])]
    public function createSecondYear(
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
            return $this->json(['error' => "Guruh, fan yoki o‘qituvchi topilmadi"], 404);
        }

        if ($gstRepository->existsByGroupAndSubject($groupId, $subjectId)) {
            return $this->json(['error' => "Bu guruhga bu fan allaqachon boshqa o‘qituvchiga biriktirilgan"], 400);
        }


        if ($group->getCourse()->getNumber() !== 2) {
            return $this->json(['error' => "Bu metod faqat 2-kurslar uchun"], 400);
        }
        if (!$gstRepository->canTeacherTakeMoreSubjects($teacherId)) {
            return $this->json([
                'error' => "Oʻqituvchining dars yuki toʻla (maksimum 10 soat)"
            ], 400);
        }

        $facultyId = $subject->getFaculty()->getId();


        $firstSection = [1, 2, 3];
        $secondSection = [4, 5, 6];

        $validFaculties = in_array($facultyId, $firstSection) ? $firstSection :
            (in_array($facultyId, $secondSection) ? $secondSection : []);

        if (empty($validFaculties)) {
            return $this->json(['error' => "Fan noma’lum fakultet toifasiga tegishli"], 400);
        }


        if (!in_array($teacher->getFaculty()->getId(), $validFaculties)) {
            return $this->json(['error' => "Bu fan uchun tanlangan ustoz ushbu fakultetlar guruhiga tegishli emas"], 400);
        }

        $gst = new GroupSubjectTeacher();
        $gst->setGroup($group);
        $gst->setSubject($subject);
        $gst->setTeacher($teacher);

        $em->persist($gst);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "2-kurs uchun to‘g‘ri biriktirildi",
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

    #[Route('/group/{groupId}', methods: ['GET'])]
    public function getByGroup(int $groupId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findByGroup($groupId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }
    #[Route('/subjects/by-group/{groupId}', methods: ['GET'])]
    public function getSubjectsByGroup(
        int $groupId,
        GroupRepository $groupRepository,
        SubjectRepository $subjectRepository
    ): JsonResponse {
        $group = $groupRepository->find($groupId);

        if (!$group) {
            return $this->json(['error' => 'Guruh topilmadi'], 404);
        }

        $facultyId = $group->getFaculty()->getId();
        $subjects = $subjectRepository->findSecondYearSubjectsForGroupFaculty($facultyId);

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


    #[Route('/teacher/{teacherId}', methods: ['GET'])]
    public function getByTeacher(int $teacherId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findByTeacher($teacherId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    #[Route('/subject/{subjectId}', methods: ['GET'])]
    public function getBySubject(int $subjectId, GroupSubjectTeacherRepository $gstRepository): JsonResponse
    {
        $assignments = $gstRepository->findBySubject($subjectId);

        return $this->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, GroupSubjectTeacherRepository $gstRepository, EntityManagerInterface $em): JsonResponse
    {
        $assignment = $gstRepository->find($id);

        if (!$assignment) {
            return $this->json(['error' => 'Biriktirish topilmadi'], 404);
        }

        $em->remove($assignment);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Biriktirish muvaffaqiyatli o\'chirildi'
        ]);
    }
    #[Route('/third-year', methods: ['POST'])]
    public function createThirdYear(
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
            return $this->json(['error' => "Bu guruhga bu fan allaqachon boshqa o‘qituvchiga biriktirilgan"], 400);
        }

        if ($group->getCourse()->getNumber() !== 3) {
            return $this->json(['error' => "Bu metod faqat 3-kurslar uchun"], 400);
        }
        if (!$gstRepository->canTeacherTakeMoreSubjects($teacherId)) {
            return $this->json([
                'error' => "Oʻqituvchining dars yuki toʻla (maksimum 10 soat)"
            ], 400);
        }

        $facultyId = $subject->getFaculty()->getId();


        $firstSection = [1, 2];
        $secondSection = [3, 4];
        $thirdSection = [5, 6];

        $validFaculties = in_array($facultyId, $firstSection) ? $firstSection :
            (in_array($facultyId, $secondSection) ? $secondSection :
                (in_array($facultyId, $thirdSection) ? $thirdSection : []));

        if (empty($validFaculties)) {
            return $this->json(['error' => "Fan noma’lum fakultet toifasiga tegishli"], 400);
        }

        if (!in_array($teacher->getFaculty()->getId(), $validFaculties)) {
            return $this->json(['error' => "Bu fan uchun tanlangan ustoz ushbu fakultetlar guruhiga tegishli emas"], 400);
        }

        $gst = new GroupSubjectTeacher();
        $gst->setGroup($group);
        $gst->setSubject($subject);
        $gst->setTeacher($teacher);

        $em->persist($gst);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "3-kurs uchun to‘g‘ri biriktirildi",
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

    #[Route('/subjects/third-year/by-group/{groupId}', methods: ['GET'])]
    public function getThirdYearSubjectsByGroup(
        int $groupId,
        GroupRepository $groupRepository,
        SubjectRepository $subjectRepository
    ): JsonResponse {
        $group = $groupRepository->find($groupId);

        if (!$group) {
            return $this->json(['error' => 'Guruh topilmadi'], 404);
        }

        $facultyId = $group->getFaculty()->getId();
        $subjects = $subjectRepository->findThirdYearSubjectsForGroupFaculty($facultyId);

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
            return $this->json(['error' => "Guruh, fan yoki o‘qituvchi topilmadi"], 404);
        }

        if ($gstRepository->existsByGroupAndSubject($groupId, $subjectId)) {
            return $this->json(['error' => "Bu guruhga bu fan allaqachon boshqa o‘qituvchiga biriktirilgan"], 400);
        }

        if ($group->getCourse()->getNumber() !== 4) {
            return $this->json(['error' => "Bu metod faqat 4-kurslar uchun"], 400);
        }


        if ($teacher->getFaculty()->getId() !== $subject->getFaculty()->getId()) {
            return $this->json(['error' => "Ustoz faqat o‘z fakulteti fanini o‘qita oladi"], 400);
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
            'message' => "4-kurs uchun to‘g‘ri biriktirildi",
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
}
