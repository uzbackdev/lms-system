<?php

namespace App\Tests\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Person;
use App\Entity\Faculty;
use Symfony\Component\HttpFoundation\Response;

class SubjectControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $faculty;
    private $adminUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine')->getManager();

        $this->cleanDatabase();

        $this->createTestData();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'deadline',
            'group_subject_teacher',
            'student',
            'teacher',
            'person',
            'subject',
            'student_group',
            'course',
            'faculty',
        ];

        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DELETE FROM {$table}");
            } catch (\Exception $e) {
            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->em->clear();
    }

    private function createTestData(): void
    {
        $this->faculty = new Faculty();
        $this->faculty->setName('Matematika fakulteti');
        $this->em->persist($this->faculty);

        $hasher = $this->client->getContainer()->get('security.user_password_hasher');
        $this->adminUser = new Person();

        $shortLogin = 'adm_' . rand(100, 999);
        $this->adminUser->setLogin($shortLogin);
        $this->adminUser->setPassword($hasher->hashPassword($this->adminUser, 'admin123'));
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->em->persist($this->adminUser);

        $this->em->flush();
    }

    public function testAdminCanCreateSubject(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'Login muvaffaqiyatsiz: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi. Login muvaffaqiyatsiz bo\'ldi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Algebra',
                'course_number' => 1
            ])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode(),
            'Subject yaratish muvaffaqiyatsiz: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Fan yaratildi', $data['message']);

        $subjectData = $data['subject'];
        $this->assertEquals('Algebra', $subjectData['name']);
        $this->assertEquals('Matematika fakulteti', $subjectData['department']);
        $this->assertEquals(1, $subjectData['course_number']);
        $this->assertTrue($subjectData['is_common_first_year']);
    }

    public function testCannotCreateSubjectWithoutRequiredFields(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'course_number' => 1
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Fizika'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCannotCreateDuplicateSubject(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Organik kimyo',
                'course_number' => 2
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Organik kimyo',
                'course_number' => 3
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCourseNumberMustBeBetween1And4(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Dasturlash',
                'course_number' => 0
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Ma\'lumotlar bazasi',
                'course_number' => 5
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testStudentCannotCreateSubject(): void
    {
        $hasher = $this->client->getContainer()->get('security.user_password_hasher');
        $student = new Person();
        $studentLogin = 'stu_' . rand(100, 999);
        $student->setLogin($studentLogin);
        $student->setPassword($hasher->hashPassword($student, 'student123'));
        $student->setRoles(['ROLE_STUDENT']);

        $this->em->persist($student);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $student->getLogin(),
                'password' => 'student123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Student token olinmadi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => $this->faculty->getId(),
                'subject_name' => 'Jahon tarixi',
                'course_number' => 1
            ])
        );

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertNotEquals(Response::HTTP_OK, $status);
        $this->assertTrue(
            $status === Response::HTTP_FORBIDDEN || $status === Response::HTTP_UNAUTHORIZED,
            "Expected 403 Forbidden or 401 Unauthorized but got $status"
        );
    }

    public function testCreateSubjectWithInvalidFaculty(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi.');
        }

        $this->client->request(
            'POST',
            '/admin/create-subject',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'faculty_id' => 999999,
                'subject_name' => 'Test fan',
                'course_number' => 1
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateSubjectWithValidCourseNumbers(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $this->adminUser->getLogin(),
                'password' => 'admin123'
            ])
        );

        $token = json_decode($this->client->getResponse()->getContent(), true)['token'] ?? null;

        if (!$token) {
            $this->fail('Token olinmadi.');
        }

        $validCourseNumbers = [1, 2, 3, 4];

        foreach ($validCourseNumbers as $courseNumber) {
            $subjectName = "Test Fan {$courseNumber}";

            $this->client->request(
                'POST',
                '/admin/create-subject',
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                ],
                json_encode([
                    'faculty_id' => $this->faculty->getId(),
                    'subject_name' => $subjectName,
                    'course_number' => $courseNumber
                ])
            );

            $this->assertEquals(
                Response::HTTP_OK,
                $this->client->getResponse()->getStatusCode(),
                "Course number {$courseNumber} uchun test muvaffaqiyatsiz: " . $this->client->getResponse()->getContent()
            );

            $responseData = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertTrue($responseData['success']);

            if ($courseNumber === 1) {
                $this->assertTrue($responseData['subject']['is_common_first_year']);
            } else {
                $this->assertFalse($responseData['subject']['is_common_first_year']);
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }

        $this->client = null;
    }
}
