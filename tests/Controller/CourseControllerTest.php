<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CourseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CourseRepository $courseRepository;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->courseRepository = self::getContainer()->get(CourseRepository::class);

        // Har testdan oldin database ni TO'LIQ tozalash
        $this->clearDatabase();
    }



    // Yoki TRUNCATE bilan qilish (tezroq)
    private function clearDatabase(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // TRUNCATE - bu DELETE dan tezroq
        $connection->executeStatement('TRUNCATE TABLE student_group');
        $connection->executeStatement('TRUNCATE TABLE group_subject_teacher');
        $connection->executeStatement('TRUNCATE TABLE admin');
        $connection->executeStatement('TRUNCATE TABLE teacher');
        $connection->executeStatement('TRUNCATE TABLE student');
        $connection->executeStatement('TRUNCATE TABLE person');
        $connection->executeStatement('TRUNCATE TABLE course');

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->em->flush();
    }

    private function cleanUpTestData(): void
    {
        // Test kurslarini o'chirish
        $testCourses = $this->courseRepository->findBy(['number' => [1, 2, 999, -1]]);
        foreach ($testCourses as $course) {
            $this->em->remove($course);
        }
        $this->em->flush();
    }

    public function testCreateCourseSuccess(): void
    {
        // 1. Yangi kurs yaratish
        $this->client->request('POST', '/create-course/1');

        // 2. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 3. Response strukturasini tekshirish
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('number', $responseData);
        $this->assertEquals(1, $responseData['number']);

        // 4. Database da yaratilganligini tekshirish
        $course = $this->courseRepository->find($responseData['id']);
        $this->assertNotNull($course);
        $this->assertEquals(1, $course->getNumber());
    }

    public function testCreateCourseDuplicate(): void
    {
        // 1. Avval kurs yaratish
        $course = new Course();
        $course->setNumber(2);
        $this->em->persist($course);
        $this->em->flush();

        // 2. Xuddi shu raqam bilan yana kurs yaratishga urinish
        $this->client->request('POST', '/create-course/2');

        // 3. Xato response ni tekshirish
        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Bu kurs allaqachon yaratilgan', $responseData);
    }

    public function testCreateCourseWithInvalidMethod(): void
    {
        // GET so'rovi bilan urinish
        $this->client->request('GET', '/create-course/1');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCreateCourseWithNegativeNumber(): void
    {
        // Manfiy raqam bilan kurs yaratish
        $this->client->request('POST', '/create-course/-1');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(-1, $responseData['number']);
    }

    public function testCreateCourseWithLargeNumber(): void
    {
        // Katta raqam bilan kurs yaratish
        $this->client->request('POST', '/create-course/999');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(999, $responseData['number']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Agar alohida cleanup kerak bo'lsa
        // $this->cleanUpTestData();
    }
}
