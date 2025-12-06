<?php

namespace App\Controller\Student;

use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/student')]
class StudentAuthController extends AbstractController
{
    #[Route('/change-password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var Person $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        $data = json_decode($request->getContent(), true);

        $oldPassword = $data['oldPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';

        if (!$passwordHasher->isPasswordValid($user, $oldPassword)) {
            return $this->json(['error' => 'Eski parol notoʻgʻri'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->json(['error' => 'Yangi parol va tasdiqlash paroli mos kemadi'], 400);
        }

        if ($passwordHasher->isPasswordValid($user, $newPassword)) {
            return $this->json(['error' => 'Bu parol eski paroldan farq qilishi kerak'], 400);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Parol yangilandi'
        ]);
    }
}
