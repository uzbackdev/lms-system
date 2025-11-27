<?php

namespace App\Controller;

use App\Entity\Group;
use App\Repository\CourseRepository;
use App\Repository\FacultyRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class GroupController extends AbstractController
{
    #[Route('/create-group/{number}', methods: ['POST'])]
    public function createGroup(
        Request $request,
        CourseRepository $courseRepository,
        FacultyRepository $facultyRepository,
        GroupRepository $groupRepository,
        int $number,
        EntityManagerInterface $em
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);
        $courseId = $data['course_id'] ?? null;
        $facultyId = $data['faculty_id'] ?? null;

        if (!$courseId || !$facultyId) {
            return $this->json("course_id VA faculty_id kiritilishi kerak", 400);
        }

        $existGroup = $groupRepository->findOneBy(['group_number' => $number]);
        $course = $courseRepository->find($courseId);
        $faculty = $facultyRepository->find($facultyId);

        if ($existGroup) {
            return $this->json("Bunday guruh avvaldan mavjud", 400);
        }

        if (!$course || !$faculty) {
            return $this->json("Kurs yoki Fakultet topilmadi", 404);
        }

        $group = new Group();
        $group->setGroupNumber($number);
        $group->setCourse($course);
        $group->setFaculty($faculty);

        $em->persist($group);
        $em->flush();

        return $this->json([
            'id' => $group->getId(),
            'number' => $group->getGroupNumber(),
            'course' => $group->getCourse()->getNumber(),
            'faculty' => $group->getFaculty()->getName()
        ]);
    }
}
