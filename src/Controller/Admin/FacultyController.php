<?php

namespace App\Controller\Admin;

use App\Entity\Faculty;
use App\Repository\FacultyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
#[Route('/admin')]
class FacultyController extends AbstractController
{
    #[Route('/create-faculty', methods: ['POST'])]
    public function createFaculty(
        EntityManagerInterface $em,
        Request $request,
        FacultyRepository $facultyRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $facultyName = $data['name'] ?? null;

        if (!$facultyName) {
            return $this->json("Name kiritilmagan", 400);
        }

        $existFaculty = $facultyRepository->findOneBy(['name' => $facultyName]);
        if ($existFaculty) {
            return $this->json("Bu fakultet allaqachon mavjud", 400);
        }

        $faculty = new Faculty();
        $faculty->setName($facultyName);

        $em->persist($faculty);
        $em->flush();

        return $this->json([
            'id' => $faculty->getId(),
            'name' => $faculty->getName()
        ]);
    }
}
