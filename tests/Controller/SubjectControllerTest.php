<?php

namespace App\Tests\Controller;

use App\Entity\Faculty;
use App\Entity\Subject;
use App\Repository\FacultyRepository;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubjectControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private SubjectRepository $subjectRepository;
    private FacultyRepository $facultyRepository;
    private Faculty $testFaculty;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->subjectRepository = self::getContainer()->get(SubjectRepository::class);
        $this->facultyRepository = self::getContainer()->get(FacultyRepository::class);

        // Transaction boshlash - ENG YAXSHI YECHIM
        $this->em->getConnection()->beginTransaction();

        // Test uchun faculty yaratish
        $this->createTestFaculty();
    }

    private function createTestFaculty(): void
    {
        // Test fakulteti
        $this->testFaculty = new Faculty();
        $this->testFaculty->setName('Test Fakulteti');
        $this->em->persist($this->testFaculty);
        $this->em->flush();
    }

    // Agar transaction ishlamasa, to'liq tozalash metodi:
    private function clearDatabase(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // Barcha bog'liq jadvallarni tozalash
        $connection->executeStatement('DELETE FROM group_subject_teacher');
        $connection->executeStatement('DELETE FROM student_group');
        $connection->executeStatement('DELETE FROM subject');
        $connection->executeStatement('DELETE FROM faculty');
        $connection->executeStatement('DELETE FROM course');
        $connection->executeStatement('DELETE FROM admin');
        $connection->executeStatement('DELETE FROM teacher');
        $connection->executeStatement('DELETE FROM student');
        $connection->executeStatement('DELETE FROM person');

        // AUTO_INCREMENT larni qayta o'rnatish
        $connection->executeStatement('ALTER TABLE faculty AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE subject AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE course AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE person AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE admin AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE teacher AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE student AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE student_group AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE group_subject_teacher AUTO_INCREMENT = 1');

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->em->flush();
    }

    public function testCreateSubjectSuccess(): void
    {
        // 1. Yangi fan yaratish
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Matematika',
                'course_number' => 1
            ])
        );

        // 2. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 3. Response strukturasini tekshirish
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('subject', $responseData);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Fan yaratildi', $responseData['message']);

        $subjectData = $responseData['subject'];
        $this->assertArrayHasKey('id', $subjectData);
        $this->assertArrayHasKey('name', $subjectData);
        $this->assertArrayHasKey('department', $subjectData);
        $this->assertArrayHasKey('course_number', $subjectData);
        $this->assertArrayHasKey('is_common_first_year', $subjectData);

        $this->assertEquals('Matematika', $subjectData['name']);
        $this->assertEquals('Test Fakulteti', $subjectData['department']);
        $this->assertEquals(1, $subjectData['course_number']);
        $this->assertTrue($subjectData['is_common_first_year']);

        // 4. Database da yaratilganligini tekshirish
        $subject = $this->subjectRepository->find($subjectData['id']);
        $this->assertNotNull($subject);
        $this->assertEquals('Matematika', $subject->getSubjectName());
        $this->assertEquals(1, $subject->getCourseNumber());
        $this->assertTrue($subject->isCommonFirstYear());
        $this->assertEquals($this->testFaculty->getId(), $subject->getFaculty()->getId());
    }

    public function testCreateSubjectDuplicate(): void
    {
        // 1. Avval fan yaratish
        $subject = new Subject();
        $subject->setSubjectName('Fizika');
        $subject->setCourseNumber(1);
        $subject->setFaculty($this->testFaculty);
        $subject->setIsCommonFirstYear(true);
        $this->em->persist($subject);
        $this->em->flush();

        // 2. Xuddi shu nom bilan yana fan yaratishga urinish
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Fizika', // Xuddi shu nom
                'course_number' => 2
            ])
        );

        // 3. Xato response ni tekshirish
        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Bu fan nomi allaqachon mavjud', $responseData);
    }

    public function testCreateSubjectWithoutSubjectName(): void
    {
        // subject_name siz so'rov yuborish
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'course_number' => 1
                // subject_name yo'q
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('subject_name va course_number kiritilishi kerak', $responseData);
    }

    public function testCreateSubjectWithoutCourseNumber(): void
    {
        // course_number siz so'rov yuborish
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Kimyo'
                // course_number yo'q
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('subject_name va course_number kiritilishi kerak', $responseData);
    }

    public function testCreateSubjectWithInvalidCourseNumber(): void
    {
        // Noto'g'ri kurs raqami bilan so'rov
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Tarix',
                'course_number' => 5 // Noto'g'ri kurs raqami
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Kurs raqami faqat 1 dan 4 gacha bo\'ladi.', $responseData);
    }

    public function testCreateSubjectWithZeroCourseNumber(): void
    {
        // 0 kurs raqami bilan so'rov
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Biologiya',
                'course_number' => 0
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('subject_name va course_number kiritilishi kerak', $responseData);
    }

    public function testCreateSubjectWithNegativeCourseNumber(): void
    {
        // Manfiy kurs raqami bilan so'rov
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Geografiya',
                'course_number' => -1
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Kurs raqami faqat 1 dan 4 gacha bo\'ladi.', $responseData);
    }

    public function testCreateSubjectWithInvalidFaculty(): void
    {
        // Mavjud bo'lmagan faculty_id bilan so'rov
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => 999, // Mavjud emas
                'subject_name' => 'Informatika',
                'course_number' => 1
            ])
        );

        $this->assertResponseStatusCodeSame(404);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Kafedra topilmadi', $responseData);
    }

    public function testCreateSubjectForFirstCourse(): void
    {
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Matematika',
                'course_number' => 1
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Fan yaratildi', $responseData['message']);
        $this->assertEquals(1, $responseData['subject']['course_number']);
        $this->assertTrue($responseData['subject']['is_common_first_year']); // 1-kurs - true bo'lishi kerak
        $this->assertEquals('Matematika', $responseData['subject']['name']);
    }

    public function testCreateSubjectForSecondCourse(): void
    {
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Dasturlash asoslari',
                'course_number' => 2
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals(2, $responseData['subject']['course_number']);
        $this->assertFalse($responseData['subject']['is_common_first_year']); // 2-kurs - false bo'lishi kerak
    }

    public function testCreateSubjectForThirdCourse(): void
    {
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Ma\'lumotlar bazasi',
                'course_number' => 3
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals(3, $responseData['subject']['course_number']);
        $this->assertFalse($responseData['subject']['is_common_first_year']); // 3-kurs - false bo'lishi kerak
    }

    public function testCreateSubjectForFourthCourse(): void
    {
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId(),
                'subject_name' => 'Sun\'iy intellekt',
                'course_number' => 4
            ])
        );

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals(4, $responseData['subject']['course_number']);
        $this->assertFalse($responseData['subject']['is_common_first_year']); // 4-kurs - false bo'lishi kerak
    }

    public function testCreateSubjectWithInvalidMethod(): void
    {
        // GET so'rovi bilan urinish
        $this->client->request('GET', '/create-subject');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCreateSubjectWithEmptyData(): void
    {
        // Bo'sh so'rov yuborish
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('subject_name va course_number kiritilishi kerak', $responseData);
    }

    public function testCreateSubjectWithNullValues(): void
    {
        // Null qiymatlar bilan so'rov
        $this->client->request(
            'POST',
            '/create-subject',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => null,
                'subject_name' => null,
                'course_number' => null
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('subject_name va course_number kiritilishi kerak', $responseData);
    }

    protected function tearDown(): void
    {
        // Transaction ni rollback qilish - barcha o'zgarishlar bekor qilinadi
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollBack();
        }

        parent::tearDown();
    }
}
