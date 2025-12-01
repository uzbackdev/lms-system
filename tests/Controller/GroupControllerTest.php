<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Repository\CourseRepository;
use App\Repository\FacultyRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GroupControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private GroupRepository $groupRepository;
    private CourseRepository $courseRepository;
    private FacultyRepository $facultyRepository;
    private Course $testCourse;
    private Faculty $testFaculty;
    private Course $testCourse2;
    private Faculty $testFaculty2;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->groupRepository = self::getContainer()->get(GroupRepository::class);
        $this->courseRepository = self::getContainer()->get(CourseRepository::class);
        $this->facultyRepository = self::getContainer()->get(FacultyRepository::class);

        // Har testdan oldin database ni tozalash
        $this->clearDatabase();

        // Test uchun course va faculty yaratish
        $this->createTestCourseAndFaculty();
    }

    private function clearDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM student_group');
        $connection->executeStatement('DELETE FROM course');
        $connection->executeStatement('DELETE FROM faculty');
        $connection->executeStatement('ALTER TABLE course AUTO_INCREMENT = 1');
        $connection->executeStatement('ALTER TABLE faculty AUTO_INCREMENT = 1');
        $this->em->flush();
    }

    private function createTestCourseAndFaculty(): void
    {
        // Test kursi
        $this->testCourse = new Course();
        $this->testCourse->setNumber(1);
        $this->em->persist($this->testCourse);

        // Test fakulteti
        $this->testFaculty = new Faculty();
        $this->testFaculty->setName('Dasturiy injiniring');
        $this->em->persist($this->testFaculty);

        // 2-kurs va 2-fakultet
        $this->testCourse2 = new Course();
        $this->testCourse2->setNumber(2);
        $this->em->persist($this->testCourse2);

        $this->testFaculty2 = new Faculty();
        $this->testFaculty2->setName('Axborot texnologiyalari');
        $this->em->persist($this->testFaculty2);

        $this->em->flush();

        // ID larni tekshirish
        // dump($this->testCourse->getId(), $this->testFaculty->getId()); // ID larni ko'rish uchun
    }

    public function testCreateGroupSuccess(): void
    {
        // 1. Yangi guruh yaratish - ID larni dynamic olish
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse->getId(),
                'faculty_id' => $this->testFaculty->getId()
            ])
        );

        // 2. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 3. Response strukturasini tekshirish
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('number', $responseData);
        $this->assertArrayHasKey('course', $responseData);
        $this->assertArrayHasKey('faculty', $responseData);

        $this->assertEquals(311, $responseData['number']);
        $this->assertEquals(1, $responseData['course']);
        $this->assertEquals('Dasturiy injiniring', $responseData['faculty']);

        // 4. Database da yaratilganligini tekshirish
        $group = $this->groupRepository->find($responseData['id']);
        $this->assertNotNull($group);
        $this->assertEquals(311, $group->getGroupNumber());
        $this->assertEquals(1, $group->getCourse()->getNumber());
        $this->assertEquals('Dasturiy injiniring', $group->getFaculty()->getName());
    }

    public function testCreateGroupDuplicate(): void
    {
        // 1. Avval guruh yaratish
        $group = new Group();
        $group->setGroupNumber(311);
        $group->setCourse($this->testCourse);
        $group->setFaculty($this->testFaculty);
        $this->em->persist($group);
        $this->em->flush();

        // 2. Xuddi shu raqam bilan yana guruh yaratishga urinish
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse->getId(),
                'faculty_id' => $this->testFaculty->getId()
            ])
        );

        // 3. Xato response ni tekshirish
        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Bunday guruh avvaldan mavjud', $responseData);
    }

    public function testCreateGroupWithoutCourseId(): void
    {
        // course_id siz so'rov yuborish
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'faculty_id' => $this->testFaculty->getId()
                // course_id yo'q
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('course_id VA faculty_id kiritilishi kerak', $responseData);
    }

    public function testCreateGroupWithoutFacultyId(): void
    {
        // faculty_id siz so'rov yuborish
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse->getId()
                // faculty_id yo'q
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('course_id VA faculty_id kiritilishi kerak', $responseData);
    }

    public function testCreateGroupWithInvalidCourse(): void
    {
        // Mavjud bo'lmagan course_id bilan so'rov
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => 999, // Mavjud emas
                'faculty_id' => $this->testFaculty->getId()
            ])
        );

        $this->assertResponseStatusCodeSame(404);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Kurs yoki Fakultet topilmadi', $responseData);
    }

    public function testCreateGroupWithInvalidFaculty(): void
    {
        // Mavjud bo'lmagan faculty_id bilan so'rov
        $this->client->request(
            'POST',
            '/create-group/311',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse->getId(),
                'faculty_id' => 999 // Mavjud emas
            ])
        );

        $this->assertResponseStatusCodeSame(404);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Kurs yoki Fakultet topilmadi', $responseData);
    }

    public function testCreateGroupWithInvalidMethod(): void
    {
        // GET so'rovi bilan urinish
        $this->client->request('GET', '/create-group/311');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCreateGroupWithDifferentCourseAndFaculty(): void
    {
        // 2-kurs va 2-fakultet bilan guruh yaratish
        $this->client->request(
            'POST',
            '/create-group/212',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse2->getId(),
                'faculty_id' => $this->testFaculty2->getId()
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(212, $responseData['number']);
        $this->assertEquals(2, $responseData['course']);
        $this->assertEquals('Axborot texnologiyalari', $responseData['faculty']);
    }

    public function testCreateGroupWithNegativeNumber(): void
    {
        // Manfiy raqam bilan guruh yaratish
        $this->client->request(
            'POST',
            '/create-group/-1',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'course_id' => $this->testCourse->getId(),
                'faculty_id' => $this->testFaculty->getId()
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(-1, $responseData['number']);
    }
}
