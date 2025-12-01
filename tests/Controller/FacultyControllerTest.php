<?php

namespace App\Tests\Controller;

use App\Entity\Faculty;
use App\Repository\FacultyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FacultyControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FacultyRepository $facultyRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->facultyRepository = self::getContainer()->get(FacultyRepository::class);

        // Har testdan oldin database ni tozalash
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM faculty');
        $this->em->flush();
    }

    public function testCreateFacultySuccess(): void
    {
        // 1. Yangi fakultet yaratish
        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Dasturiy injiniring'])
        );

        // 2. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 3. Response strukturasini tekshirish
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('name', $responseData);
        $this->assertEquals('Dasturiy injiniring', $responseData['name']);

        // 4. Database da yaratilganligini tekshirish
        $faculty = $this->facultyRepository->find($responseData['id']);
        $this->assertNotNull($faculty);
        $this->assertEquals('Dasturiy injiniring', $faculty->getName());
    }

    public function testCreateFacultyDuplicate(): void
    {
        // 1. Avval fakultet yaratish
        $faculty = new Faculty();
        $faculty->setName('Axborot texnologiyalari');
        $this->em->persist($faculty);
        $this->em->flush();

        // 2. Xuddi shu nom bilan yana fakultet yaratishga urinish
        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Axborot texnologiyalari'])
        );

        // 3. Xato response ni tekshirish
        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Bu fakultet allaqachon mavjud', $responseData);
    }

    public function testCreateFacultyWithoutName(): void
    {
        // Name siz so'rov yuborish
        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => '']) // Bo'sh name
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Name kiritilmagan', $responseData);
    }

    public function testCreateFacultyWithNullName(): void
    {
        // Name bo'lmagan so'rov
        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([]) // Name yo'q
        );

        $this->assertResponseStatusCodeSame(400);
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals('Name kiritilmagan', $responseData);
    }

    public function testCreateFacultyWithInvalidMethod(): void
    {
        // GET so'rovi bilan urinish
        $this->client->request('GET', '/create-faculty');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testCreateFacultyWithLongName(): void
    {
        // Uzun nom bilan fakultet yaratish
        $longName = str_repeat('A', 255); // 255 ta belgi

        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => $longName])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals($longName, $responseData['name']);
    }

    public function testCreateFacultyWithSpecialCharacters(): void
    {
        // Maxsus belgilar bilan nom
        $specialName = 'Fakultet №1 - "IT" (Axborot)';

        $this->client->request(
            'POST',
            '/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => $specialName])
        );

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals($specialName, $responseData['name']);
    }
}
