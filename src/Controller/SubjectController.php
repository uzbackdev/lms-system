<?php

namespace App\Controller;

use App\Entity\Subject;
use App\Repository\FacultyRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SubjectController extends AbstractController
{
    #[Route('/create-subject' , methods: ['POST'])]
    public function createSubject(Request $request, FacultyRepository $departmentRepository,
                                  EntityManagerInterface $em, SubjectRepository $subjectRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $facultyId = $data['faculty_id'] ?? null;
        $subjectName = $data['subject_name'] ?? null;
        $courseNumber = $data['course_number'] ?? null;

        if (!$subjectName || !$courseNumber) {
            return $this->json("subject_name va course_number kiritilishi kerak", 400);
        }
        $existSubject = $subjectRepository->findOneBy(['subjectName' => $subjectName]);
        if ($existSubject) {
            return $this->json("Bu fan nomi allaqachon mavjud", 400);
        }
        if ($courseNumber < 1 || $courseNumber > 4) {
            return $this->json("Kurs raqami faqat 1 dan 4 gacha bo'ladi.", 400);
        }

        $faculty = $departmentRepository->find($facultyId);
        if (!$faculty) {
            return $this->json("Kafedra topilmadi", 404);
        }
        $subject = new Subject();
        $subject->setFaculty($faculty);
        $subject->setSubjectName($subjectName);
        $subject->setCourseNumber($courseNumber);


        if ($courseNumber === 1) {
            $subject->setIsCommonFirstYear(true);
        } else {
            $subject->setIsCommonFirstYear(false);
        }

        $em->persist($subject);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "Fan yaratildi",
            'subject' => [
                'id' => $subject->getId(),
                'name' => $subject->getSubjectName(),
                'department' => $faculty->getName(),
                'course_number' => $subject->getCourseNumber(),
                'is_common_first_year' => $subject->isCommonFirstYear()
            ]
        ]);
    }
}
