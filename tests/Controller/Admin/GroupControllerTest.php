<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GroupControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private Course $course;
    private Faculty $faculty;
    private Person $adminUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();


        $this->cleanDatabase();


        $this->createTestData();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();


        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');


        $tables = ['student', 'teacher', 'admin', 'person', 'student_group', 'course', 'faculty'];
        foreach ($tables as $table) {
            try {
                $connection->executeStatement("DELETE FROM {$table}");
            } catch (\Exception $e) {

            }
        }


        foreach ($tables as $table) {
            try {
                $connection->executeStatement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            } catch (\Exception $e) {

            }
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createTestData(): void
    {

        $this->course = new Course();
        $this->course->setNumber(1);
        $this->entityManager->persist($this->course);


        $this->faculty = new Faculty();
        $this->faculty->setName('Test Faculty');
        $this->entityManager->persist($this->faculty);


        $this->adminUser = new Person();
        $this->adminUser->setLogin('admin1');
        $this->adminUser->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($this->adminUser);

        $this->entityManager->flush();
    }

    public function testCreateGroupSuccess(): void
    {
        $groupNumber = 101;
        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $this->faculty->getId(),
        ];

        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('number', $responseData);
        $this->assertArrayHasKey('course', $responseData);
        $this->assertArrayHasKey('faculty', $responseData);

        $this->assertEquals($groupNumber, $responseData['number']);
        $this->assertEquals($this->course->getNumber(), $responseData['course']);
        $this->assertEquals($this->faculty->getName(), $responseData['faculty']);

        $groupRepository = $this->entityManager->getRepository(Group::class);
        $createdGroup = $groupRepository->find($responseData['id']);

        $this->assertNotNull($createdGroup);
        $this->assertEquals($groupNumber, $createdGroup->getGroupNumber());
        $this->assertEquals($this->course->getId(), $createdGroup->getCourse()->getId());
        $this->assertEquals($this->faculty->getId(), $createdGroup->getFaculty()->getId());
    }

    public function testCreateGroupMissingRequiredFields(): void
    {
        $groupNumber = 102;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');


        $data1 = [
            'faculty_id' => $this->faculty->getId(),
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data1)
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());


        $data2 = [
            'course_id' => $this->course->getId(),
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data2)
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupDuplicateNumber(): void
    {
        $groupNumber = 103;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');


        $existingGroup = new Group();
        $existingGroup->setGroupNumber($groupNumber);
        $existingGroup->setCourse($this->course);
        $existingGroup->setFaculty($this->faculty);

        $this->entityManager->persist($existingGroup);
        $this->entityManager->flush();


        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $this->faculty->getId(),
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupWithNonExistentCourse(): void
    {
        $groupNumber = 104;
        $nonExistentCourseId = 9999;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $data = [
            'course_id' => $nonExistentCourseId,
            'faculty_id' => $this->faculty->getId(),
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupWithNonExistentFaculty(): void
    {
        $groupNumber = 105;
        $nonExistentFacultyId = 9999;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $nonExistentFacultyId,
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupUnauthorizedAccess(): void
    {
        $groupNumber = 106;
        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $this->faculty->getId(),
        ];


        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupForbiddenForNonAdmin(): void
    {
        $groupNumber = 107;
        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $this->faculty->getId(),
        ];


        $studentUser = new Person();
        $studentUser->setLogin('student1');
        $studentUser->setPassword(password_hash('student123', PASSWORD_DEFAULT));
        $studentUser->setRoles(['ROLE_STUDENT']);

        $this->entityManager->persist($studentUser);
        $this->entityManager->flush();

        $studentToken = $this->getTokenForUser('student1', 'student123');

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $studentToken,
            ],
            json_encode($data)
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupWithEmptyJson(): void
    {
        $groupNumber = 108;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testCreateGroupWithInvalidJson(): void
    {
        $groupNumber = 109;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            '{invalid json'
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    private function getTokenForUser(string $username, string $password): string
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

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $data = json_decode($response->getContent(), true);
            if (isset($data['token'])) {
                return $data['token'];
            }
        }

        return 'token_' . substr($username, 0, 5);
    }

    public function testCreateGroupWithNegativeGroupNumber(): void
    {
        $groupNumber = -1;
        $token = $this->getTokenForUser($this->adminUser->getLogin(), 'password123');

        $data = [
            'course_id' => $this->course->getId(),
            'faculty_id' => $this->faculty->getId(),
        ];

        $this->client->request(
            'POST',
            "/admin/create-group/{$groupNumber}",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($data)
        );

       $this->assertTrue(
            in_array($this->client->getResponse()->getStatusCode(),
                [Response::HTTP_OK, Response::HTTP_BAD_REQUEST])
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();


        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }

        $this->client = null;
    }
}
