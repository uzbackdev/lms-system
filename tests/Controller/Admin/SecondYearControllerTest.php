<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Person;
use App\Entity\Subject;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SecondYearControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token = '';

    private const FIRST_SECTION_IDS = [1, 2, 3];
    private const SECOND_SECTION_IDS = [4, 5, 6];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();

        $this->cleanupTestData();

        $this->createTestAdminUser();

        $this->loginAndGetToken();

        $this->createSectionFaculties();
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

    private function createSectionFaculties(): void
    {
        $connection = $this->em->getConnection();

        foreach (self::FIRST_SECTION_IDS as $id) {
            $connection->executeStatement(
                "INSERT IGNORE INTO faculty (id, name) VALUES (:id, :name)",
                ['id' => $id, 'name' => 'First Section Faculty ' . $id]
            );
        }

        foreach (self::SECOND_SECTION_IDS as $id) {
            $connection->executeStatement(
                "INSERT IGNORE INTO faculty (id, name) VALUES (:id, :name)",
                ['id' => $id, 'name' => 'Second Section Faculty ' . $id]
            );
        }

        $this->em->clear();
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

    public function testGetSubjectsByGroupSuccess(): void
    {
        $faculty = $this->getFirstSectionFaculty();
        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty);

        $subject = new Subject();
        $subject->setSubjectName('Advanced Programming');
        $subject->setCourseNumber(2);
        $subject->setFaculty($faculty);
        $subject->setIsCommonFirstYear(false);

        $this->em->persist($subject);
        $this->em->flush();

        $this->client->request(
            'GET',
            '/admin/group-subject-teacher/subjects/by-group/' . $group->getId(),
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

    public function testGetSubjectsByGroupNotFound(): void
    {
        $nonExistentId = 99999;

        $this->client->request(
            'GET',
            '/admin/group-subject-teacher/subjects/by-group/' . $nonExistentId,
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
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            "Expected 404 for non-existent group"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Guruh topilmadi', $data['error']);
    }

    public function testCreateSecondYearSuccess(): void
    {
        $faculty = $this->getFirstSectionFaculty();
        $teacher = $this->createTeacher($faculty);

        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 2);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
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
        $this->assertEquals("2-kurs uchun to'g'ri biriktirildi", $data['message']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);

        $gstRepository = $this->em->getRepository(GroupSubjectTeacher::class);
        $createdGst = $gstRepository->find($data['data']['id']);
        $this->assertNotNull($createdGst);
        $this->assertEquals($group->getId(), $createdGst->getGroup()->getId());
        $this->assertEquals($subject->getId(), $createdGst->getSubject()->getId());
        $this->assertEquals($teacher->getId(), $createdGst->getTeacher()->getId());
    }

    public function testCreateSecondYearMissingParameters(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
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
        $this->assertStringContainsString('group_id, subject_id va teacher_id', $data['error']);
    }

    public function testCreateSecondYearNotFound(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
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
        $this->assertStringContainsString("topilmadi", $data['error']);
    }

    public function testCreateSecondYearDuplicateGroupSubject(): void
    {
        $faculty = $this->getFirstSectionFaculty();
        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 2);
        $teacher = $this->createTeacher($faculty);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $firstResponse = $this->client->getResponse();
        $this->assertEquals(
            Response::HTTP_OK,
            $firstResponse->getStatusCode(),
            "First creation should succeed"
        );

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            "Expected 400 for duplicate group-subject"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("allaqachon", $data['error']);
    }

    public function testCreateSecondYearNotSecondYearGroup(): void
    {
        $faculty = $this->getFirstSectionFaculty();
        $course = $this->getOrCreateCourse(1);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 2);
        $teacher = $this->createTeacher($faculty);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("2-kurslar", $data['error']);
    }

    public function testCreateSecondYearTeacherWorkloadExceeded(): void
    {
        $this->markTestSkipped('Teacher workload logic needs separate testing with mocked repository');
    }

    public function testCreateSecondYearInvalidFacultySection(): void
    {
        $faculty1 = $this->getFirstSectionFaculty();
        $faculty2 = $this->getSecondSectionFaculty();

        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty1);
        $subject = $this->createSubject($faculty1, 2);
        $teacher = $this->createTeacher($faculty2);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("fakultetlar guruhiga", $data['error']);
    }

    public function testCreateSecondYearValidFacultySection(): void
    {
        $faculty1 = $this->getFirstSectionFaculty();

        $faculty2 = null;
        foreach (self::FIRST_SECTION_IDS as $id) {
            if ($id !== $faculty1->getId()) {
                $faculty2 = $this->em->getRepository(Faculty::class)->find($id);
                if ($faculty2) {
                    break;
                }
            }
        }

        if (!$faculty2) {
            $newId = ($faculty1->getId() === 1) ? 2 : 1;
            $faculty2 = $this->em->getRepository(Faculty::class)->find($newId);
        }

        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty1);
        $subject = $this->createSubject($faculty1, 2);
        $teacher = $this->createTeacher($faculty2);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/second-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
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
            "Should succeed when teacher and subject are in same section. Response: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    private function getFirstSectionFaculty(): Faculty
    {
        foreach (self::FIRST_SECTION_IDS as $id) {
            $faculty = $this->em->getRepository(Faculty::class)->find($id);
            if ($faculty) {
                return $faculty;
            }
        }

        return $this->createFacultyWithId(1);
    }

    private function getSecondSectionFaculty(): Faculty
    {
        foreach (self::SECOND_SECTION_IDS as $id) {
            $faculty = $this->em->getRepository(Faculty::class)->find($id);
            if ($faculty) {
                return $faculty;
            }
        }

        return $this->createFacultyWithId(4);
    }

    private function createFacultyWithId(int $id): Faculty
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement(
            "INSERT INTO faculty (id, name) VALUES (:id, :name)",
            ['id' => $id, 'name' => 'SecondYearControllerTest Faculty ' . $id]
        );

        $this->em->clear();
        return $this->em->getRepository(Faculty::class)->find($id);
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
        $subject->setSubjectName('SecondYearControllerTest Subject ' . uniqid());
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
        $teacher->setName('SecondYearControllerTest');
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
