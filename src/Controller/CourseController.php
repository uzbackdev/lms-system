<?php

namespace App\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class CourseController extends AbstractController
{
    #[Route('/create-course/{courseNumber}' , methods: ['POST'])]
    public function createCourse(CourseRepository $courseRepository , EntityManagerInterface $em , int $courseNumber):JsonResponse{
        $existCourse = $courseRepository->findOneBy(['number' => $courseNumber]);
        if($existCourse){
            return $this->json(["Bu kurs allaqachon yaratilgan"] , 400);
        }
        $course = new Course();
        $course->setNumber($courseNumber);
        $em->persist($course);
        $em->flush();
        return $this->json(['id'=>$course->getId(),
            'number'=>$course->getNumber()
        ]);
    }
}
