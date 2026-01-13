<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Holiday;
use App\Entity\Semester;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SemesterControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private string $adminToken;
    private ?Person $adminUser = null;
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
        $this->adminToken = $this->getJwtToken('admin_user', 'password123');
    }

    private function initializeDatabase(): void
    {
        $connection = $this->entityManager->getConnection();

        $tables = ['holiday', 'semester'];

        foreach ($tables as $table) {
            try {
                $connection->executeQuery("DELETE FROM $table");
            } catch (\Exception $e) {
            }
        }

        try {
            $connection->executeQuery("DELETE FROM person WHERE login LIKE 'test_%' OR login = 'admin_user'");
        } catch (\Exception $e) {
        }

        $this->entityManager->clear();
    }

    private function createAdminUser(): void
    {
        $existingAdmin = $this->entityManager->getRepository(Person::class)
            ->findOneBy(['login' => 'admin_user']);

        if ($existingAdmin) {
            $this->entityManager->remove($existingAdmin);
            $this->entityManager->flush();
        }

        $this->adminUser = new Person();
        $this->adminUser->setLogin('admin_user');

        $passwordHasher = self::getContainer()->get('security.password_hasher');
        $hashedPassword = $passwordHasher->hashPassword($this->adminUser, 'password123');
        $this->adminUser->setPassword($hashedPassword);

        $this->adminUser->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($this->adminUser);
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

    public function testGetHolidays(): void
    {
        $semester = $this->createTestSemester('Test Semester 1');
        $this->createTestHoliday('Test Holiday 1', new \DateTime('2024-01-01'), $semester);
        $this->createTestHoliday('Test Holiday 2', new \DateTime('2024-03-08'), $semester);

        $this->client->request(
            'GET',
            '/admin/holidays',
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
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        foreach ($data as $holiday) {
            $this->assertArrayHasKey('id', $holiday);
            $this->assertArrayHasKey('date', $holiday);
            $this->assertArrayHasKey('name', $holiday);
            $this->assertArrayHasKey('semester', $holiday);
        }
    }

    public function testCreateHolidaySuccess(): void
    {
        $semester = $this->createTestSemester('Test Semester for Holiday');

        $holidayData = [
            'date' => '25.12.2024',
            'name' => 'Rojdestvo',
            'semester_id' => $semester->getId(),
        ];

        $this->client->request(
            'POST',
            '/admin/create-holiday',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($holidayData)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Bayram qoʻshildi', $data['message']);
        $this->assertArrayHasKey('holiday', $data);
        $this->assertEquals('Rojdestvo', $data['holiday']['name']);
        $this->assertEquals('25.12.2024', $data['holiday']['date']);
    }

    public function testCreateHolidayWithInvalidDate(): void
    {
        $semester = $this->createTestSemester('Test Semester');

        $invalidData = [
            'date' => 'invalid-date',
            'name' => 'Test Holiday',
            'semester_id' => $semester->getId(),
        ];

        $this->client->request(
            'POST',
            '/admin/create-holiday',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($invalidData)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Notoʻgʻri sana format', $data['error']);
    }

    public function testCreateHolidayWithoutName(): void
    {
        $semester = $this->createTestSemester('Test Semester');

        $dataWithoutName = [
            'date' => '25.12.2024',
            'semester_id' => $semester->getId(),
        ];

        $this->client->request(
            'POST',
            '/admin/create-holiday',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($dataWithoutName)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Bayram nomi kiritilmagan', $data['error']);
    }

    public function testCreateHolidayWithNonexistentSemester(): void
    {
        $nonExistentSemesterId = 99999;

        $data = [
            'date' => '25.12.2024',
            'name' => 'Test Holiday',
            'semester_id' => $nonExistentSemesterId,
        ];

        $this->client->request(
            'POST',
            '/admin/create-holiday',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($data)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Semester topilmadi', $data['error']);
    }

    public function testCreateSemesterSuccess(): void
    {
        $semesterData = [
            'name' => '2024-2025 Spring Semester',
            'start_date' => '2024-01-15',
            'end_date' => '2024-06-15',
            'is_active' => true,
        ];

        $this->client->request(
            'POST',
            '/admin/create-semester',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($semesterData)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Semestr muvaffaqiyatli yaratildi', $data['message']);
        $this->assertArrayHasKey('semester', $data);
        $this->assertEquals('2024-2025 Spring Semester', $data['semester']['name']);
        $this->assertTrue($data['semester']['is_active']);
    }

    public function testCreateSemesterWithMissingFields(): void
    {
        $incompleteData = [
            'name' => 'Test Semester',
        ];

        $this->client->request(
            'POST',
            '/admin/create-semester',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($incompleteData)
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('to\'ldirilishi shart', $data['error']);
    }

    public function testGetSemesters(): void
    {
        $this->createTestSemester('Semester 1');
        $this->createTestSemester('Semester 2');
        $this->createTestSemester('Semester 3');

        $this->client->request(
            'GET',
            '/admin/semesters',
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
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('semesters', $data);
        $this->assertIsArray($data['semesters']);
        $this->assertGreaterThanOrEqual(3, count($data['semesters']));

        foreach ($data['semesters'] as $semester) {
            $this->assertArrayHasKey('id', $semester);
            $this->assertArrayHasKey('name', $semester);
            $this->assertArrayHasKey('start_date', $semester);
            $this->assertArrayHasKey('end_date', $semester);
            $this->assertArrayHasKey('is_active', $semester);
        }
    }

    public function testActivateSemesterSuccess(): void
    {
        $this->clearAllSemesters();

        $semester1 = $this->createTestSemester('Semester 1', false);
        $semester2 = $this->createTestSemester('Semester 2', false);

        $this->entityManager->clear();

        $activeSemesters = $this->entityManager->getRepository(Semester::class)
            ->findBy(['isActive' => true]);
        $this->assertCount(0, $activeSemesters,
            'Initial active semesters should be 0, but found: ' . count($activeSemesters)
        );

        $this->client->request(
            'POST',
            '/admin/semester/' . $semester1->getId() . '/activate',
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
        $this->assertTrue($data['success']);
        $this->assertEquals('Semestr faol holatga o\'tkazildi', $data['message']);

        $this->entityManager->clear();
        $activeSemesters = $this->entityManager->getRepository(Semester::class)
            ->findBy(['isActive' => true]);
        $this->assertCount(1, $activeSemesters);
        $this->assertEquals($semester1->getId(), $activeSemesters[0]->getId());
    }

    private function clearAllSemesters(): void
    {
        $connection = $this->entityManager->getConnection();

        try {
            $connection->executeQuery('DELETE FROM holiday');
        } catch (\Exception $e) {
        }

        try {
            $connection->executeQuery('DELETE FROM semester');
        } catch (\Exception $e) {
        }

        $this->entityManager->clear();
    }

    public function testActivateSemesterSuccessAlternative(): void
    {
        $this->clearAllSemesters();

        $semester1 = $this->createTestSemester('Semester 1', false);
        $semester2 = $this->createTestSemester('Semester 2', false);

        $this->entityManager->clear();
        $allSemesters = $this->entityManager->getRepository(Semester::class)->findAll();
        $activeCount = 0;

        foreach ($allSemesters as $sem) {
            if ($sem->isActive()) {
                $activeCount++;
                echo "\nActive semester found before test: ID=" . $sem->getId() . ", Name=" . $sem->getName();
            }
        }

        $this->assertEquals(0, $activeCount, "Found $activeCount active semesters before activation");

        $this->client->request(
            'POST',
            '/admin/semester/' . $semester1->getId() . '/activate',
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
        $this->assertTrue($data['success']);
    }

    public function testActivateNonexistentSemester(): void
    {
        $nonExistentId = 99999;

        $this->client->request(
            'POST',
            '/admin/semester/' . $nonExistentId . '/activate',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Semestr topilmadi', $data['error']);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->client->request(
            'GET',
            '/admin/semesters',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testAccessWithWrongRole(): void
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

        $studentToken = $this->getJwtToken('test_student', 'student123');

        $this->client->request(
            'GET',
            '/admin/semesters',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $studentToken,
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function createTestSemester(string $name, bool $isActive = false): Semester
    {
        $semester = new Semester();
        $semester->setName($name);
        $semester->setStartDate(new \DateTime('2024-01-01'));
        $semester->setEndDate(new \DateTime('2024-12-31'));
        $semester->setIsActive($isActive);

        $this->entityManager->persist($semester);
        $this->entityManager->flush();

        return $semester;
    }

    private function createTestHoliday(string $name, \DateTime $date, Semester $semester): Holiday
    {
        $holiday = new Holiday();
        $holiday->setName($name);
        $holiday->setDate($date);
        $holiday->setSemester($semester);

        $this->entityManager->persist($holiday);
        $this->entityManager->flush();

        return $holiday;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        unset($this->client, $this->entityManager, $this->adminUser);
    }

    public static function tearDownAfterClass(): void
    {
        self::$databaseInitialized = false;
    }
}
