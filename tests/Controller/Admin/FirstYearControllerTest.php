<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\Person;
use App\Entity\Subject;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FirstYearControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();

        $this->cleanupTestData();

        $this->createTestAdminUser();

        $this->loginAndGetToken();
    }

    private function cleanupTestData(): void
    {
        $connection = $this->em->getConnection();

        try {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            $tables = [
                'group_subject_teacher',
                'deadline',
                'student_group',
                'subject',
                'teacher',
                'student_group',
                'faculty',
                'course',
                'person'
            ];

            foreach ($tables as $table) {
                try {
                    if ($table === 'person') {
                        $connection->executeStatement("DELETE FROM person WHERE login LIKE 'test_%'");
                    } else {
                        $connection->executeStatement("DELETE FROM $table");
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        } catch (\Exception $e) {
        }
    }

    private function createTestAdminUser(): void
    {
        $personRepository = $this->em->getRepository(Person::class);

        $adminPerson = $personRepository->findOneBy(['login' => 'test_admin']);

        if (!$adminPerson) {
            $adminPerson = new Person();
            $adminPerson->setLogin('test_admin');

            $passwordHasher = $this->client->getContainer()->get('security.user_password_hasher');
            $hashedPassword = $passwordHasher->hashPassword($adminPerson, 'test_password');
            $adminPerson->setPassword($hashedPassword);

            $adminPerson->setRoles(['ROLE_ADMIN']);

            $this->em->persist($adminPerson);
            $this->em->flush();
        }
    }

    private function loginAndGetToken(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'login' => 'test_admin',
                'password' => 'test_password'
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Login failed. Status: " . $response->getStatusCode() . ", Body: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('token', $data, "Token response'da yo'q. Response: " . json_encode($data));

        $this->token = $data['token'];
        $this->assertNotEmpty($this->token, "Token bo'sh");
    }

    public function testGetFirstYearSubjects(): void
    {
        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(1);

        $subject1 = new Subject();
        $subject1->setSubjectName('Mathematics');
        $subject1->setCourseNumber(1);
        $subject1->setFaculty($faculty);
        $subject1->setIsCommonFirstYear(true);

        $this->em->persist($subject1);
        $this->em->flush();

        $this->client->request(
            'GET',
            '/admin/group-subject-teacher/subjects/first-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "GET request failed. Expected 200, got " . $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success'], "Success false. Response: " . json_encode($data));
        $this->assertIsArray($data['data']);
    }

    public function testCreateFirstYearSuccess(): void
    {
        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(1);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 1);
        $teacher = $this->createTeacher($faculty);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/first-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Create failed. Expected 200, got " . $response->getStatusCode() . ", Body: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals("1-kurs uchun to'g'ri biriktirildi", $data['message']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);
    }

    public function testCreateFirstYearMissingParameters(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/first-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => 1,
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCreateFirstYearNotFound(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/first-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => 99999,
                'subject_id' => 99999,
                'teacher_id' => 99999,
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    private function createFaculty(): Faculty
    {
        $faculty = new Faculty();
        $faculty->setName('ThirdYearControllerTest Faculty ' . uniqid());

        $this->em->persist($faculty);
        $this->em->flush();

        return $faculty;
    }

    private function getOrCreateCourse(int $number): Course
    {
        $courseRepository = $this->em->getRepository(Course::class);

        $existingCourse = $courseRepository->findOneBy(['number' => $number]);

        if ($existingCourse) {
            return $existingCourse;
        }

        $course = new Course();
        $course->setNumber($number);

        $this->em->persist($course);
        $this->em->flush();

        return $course;
    }

    private function createGroup(Course $course, Faculty $faculty): Group
    {
        $group = new Group();
        $group->setGroupNumber(rand(100, 999));
        $group->setCourse($course);
        $group->setFaculty($faculty);

        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    private function createSubject(Faculty $faculty, int $courseNumber): Subject
    {
        $subject = new Subject();
        $subject->setSubjectName('ThirdYearControllerTest Subject ' . uniqid());
        $subject->setCourseNumber($courseNumber);
        $subject->setFaculty($faculty);
        $subject->setIsCommonFirstYear($courseNumber === 1);

        $this->em->persist($subject);
        $this->em->flush();

        return $subject;
    }

    private function createTeacher(Faculty $faculty): Teacher
    {
        $teacher = new Teacher();
        $teacher->setName('ThirdYearControllerTest');
        $teacher->setSurname('Teacher ' . uniqid());
        $teacher->setFaculty($faculty);

        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    protected function tearDown(): void
    {
        if ($this->em->isOpen()) {
            $this->em->clear();
        }

        parent::tearDown();
    }
}
