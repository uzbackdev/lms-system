<?php

namespace App\Controller;

use App\Repository\GroupSubjectTeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/group-subject-teacher')]
class GSTManagementController extends AbstractController
{
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(
        int $id,
        GroupSubjectTeacherRepository $gstRepository,
        EntityManagerInterface $em
    ): JsonResponse {
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


}
