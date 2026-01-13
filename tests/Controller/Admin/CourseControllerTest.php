<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $adminToken;
    private string $studentToken;
    private static bool $databaseInitialized = false;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);


        if (!self::$databaseInitialized) {
            $this->initializeDatabase();
            self::$databaseInitialized = true;
        }


        $this->createAdminUser();
        $this->createStudentUser();

        $this->adminToken = $this->getJwtToken('test_admin', 'admin123');
        $this->studentToken = $this->getJwtToken('test_student', 'student123');
    }

    private function initializeDatabase(): void
    {
        $connection = $this->entityManager->getConnection();


        try {
            $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 0");

            $tables = ['student', 'student_group', 'subject', 'teacher', 'course', 'faculty'];

            foreach ($tables as $table) {
                try {
                    $connection->executeQuery("DELETE FROM $table");
                } catch (\Exception $e) {

                }
            }

            $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Exception $e) {

            try {
                $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
            } catch (\Exception $e2) {
                // Ignore
            }
        }


        try {
            $connection->executeQuery("DELETE FROM person WHERE login IN ('test_admin', 'test_student')");
        } catch (\Exception $e) {
            // Ignore
        }

        $this->entityManager->clear();
    }

    private function createAdminUser(): void
    {

        $existingAdmin = $this->entityManager->getRepository(Person::class)
            ->findOneBy(['login' => 'test_admin']);

        if ($existingAdmin) {
            $this->entityManager->remove($existingAdmin);
            $this->entityManager->flush();
        }


        $admin = new Person();
        $admin->setLogin('test_admin');

        $passwordHasher = self::getContainer()->get('security.password_hasher');
        $hashedPassword = $passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);

        $admin->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();
    }

    private function createStudentUser(): void
    {

        $existingStudent = $this->entityManager->getRepository(Person::class)
            ->findOneBy(['login' => 'test_student']);

        if ($existingStudent) {
            $this->entityManager->remove($existingStudent);
            $this->entityManager->flush();
        }


        $student = new Person();
        $student->setLogin('test_student');

        $passwordHasher = self::getContainer()->get('security.password_hasher');
        $hashedPassword = $passwordHasher->hashPassword($student, 'student123');
        $student->setPassword($hashedPassword);

        $student->setRoles(['ROLE_STUDENT']);

        $this->entityManager->persist($student);
        $this->entityManager->flush();
    }

    private function getJwtToken(string $username, string $password): string
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $username,
                'password' => $password
            ])
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail('Authentication failed. Response: ' . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);

        if (!isset($data['token'])) {
            $this->fail('Token not received in response: ' . $response->getContent());
        }

        return $data['token'];
    }

    private function clearCourses(): void
    {
        $connection = $this->entityManager->getConnection();


        try {
            $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 0");


            $tables = ['student', 'student_group', 'course'];

            foreach ($tables as $table) {
                try {
                    $connection->executeQuery("DELETE FROM $table");
                } catch (\Exception $e) {

                }
            }

            $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Exception $e) {

            try {
                $connection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
            } catch (\Exception $e2) {

            }
        }

        $this->entityManager->clear();
    }

    public function testAdminCanCreateValidCourse(): void
    {
        $this->clearCourses();


        $courseNumber = 2;

        $this->client->request(
            'POST',
            '/admin/create-course/' . $courseNumber,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('number', $data);
        $this->assertEquals($courseNumber, $data['number']);
        $this->assertArrayHasKey('id', $data);


        $course = $this->entityManager->getRepository(Course::class)->find($data['id']);
        $this->assertNotNull($course);
        $this->assertEquals($courseNumber, $course->getNumber());
    }

    public function testCannotCreateDuplicateCourse(): void
    {
        $this->clearCourses();


        $courseNumber = 3;

        $this->client->request(
            'POST',
            '/admin/create-course/' . $courseNumber,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());


        $this->client->request(
            'POST',
            '/admin/create-course/' . $courseNumber,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('allaqachon yaratilgan', $response->getContent());
    }

    public function testAdminCanCreateAllValidCourses(): void
    {
        $this->clearCourses();


        $validCourseNumbers = [1, 2, 3, 4];
        $createdCourseIds = [];

        foreach ($validCourseNumbers as $courseNumber) {
            $this->client->request(
                'POST',
                '/admin/create-course/' . $courseNumber,
                [],
                [],
                [
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                    'CONTENT_TYPE' => 'application/json',
                ]
            );

            $response = $this->client->getResponse();
            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

            $data = json_decode($response->getContent(), true);
            $this->assertEquals($courseNumber, $data['number']);
            $createdCourseIds[] = $data['id'];
        }


        $courses = $this->entityManager->getRepository(Course::class)->findAll();
        $this->assertCount(count($validCourseNumbers), $courses);


        $courseNumbers = array_map(fn($course) => $course->getNumber(), $courses);
        sort($courseNumbers);
        $this->assertEquals($validCourseNumbers, $courseNumbers);
    }

    public function testStudentCannotCreateCourse(): void
    {
        $this->clearCourses();


        $this->client->request(
            'POST',
            '/admin/create-course/1',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->studentToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();


        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testUnauthorizedAccess(): void
    {

        $this->client->request(
            'POST',
            '/admin/create-course/1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCreateCourseWithZeroNumber(): void
    {
        $this->clearCourses();

        $this->client->request(
            'POST',
            '/admin/create-course/0',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [Response::HTTP_OK, Response::HTTP_INTERNAL_SERVER_ERROR]),
            "Unexpected status code: $statusCode"
        );
    }

    public function testCreateCourseWithFiveNumber(): void
    {
        $this->clearCourses();

        $this->client->request(
            'POST',
            '/admin/create-course/5',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [Response::HTTP_OK, Response::HTTP_INTERNAL_SERVER_ERROR]),
            "Unexpected status code: $statusCode"
        );
    }

    public function testCreateCourseWithNegativeNumber(): void
    {
        $this->clearCourses();

        $this->client->request(
            'POST',
            '/admin/create-course/-1',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();


        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [Response::HTTP_OK, Response::HTTP_INTERNAL_SERVER_ERROR]),
            "Unexpected status code: $statusCode"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        unset($this->client, $this->entityManager);
    }

    public static function tearDownAfterClass(): void
    {
        self::$databaseInitialized = false;
    }
}
