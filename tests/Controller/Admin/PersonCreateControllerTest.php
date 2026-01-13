<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Faculty;
use App\Entity\Course;
use App\Entity\Group;
use App\Repository\FacultyRepository;
use App\Repository\CourseRepository;
use App\Repository\GroupRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PersonCreateControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private FacultyRepository $facultyRepository;
    private CourseRepository $courseRepository;
    private GroupRepository $groupRepository;
    private PersonRepository $personRepository;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->facultyRepository = self::getContainer()->get(FacultyRepository::class);
        $this->courseRepository = self::getContainer()->get(CourseRepository::class);
        $this->groupRepository = self::getContainer()->get(GroupRepository::class);
        $this->personRepository = self::getContainer()->get(PersonRepository::class);

        $this->createTestData();

        $this->adminToken = $this->loginAsAdmin();
    }

    private function createTestData(): void
    {
        $faculty = new Faculty();
        $faculty->setName('Test Fakultet');
        $this->em->persist($faculty);

        $course = new Course();
        $course->setNumber(1);
        $this->em->persist($course);

        $group = new Group();
        $group->setGroupNumber(101);
        $group->setFaculty($faculty);
        $group->setCourse($course);
        $this->em->persist($group);

        $this->em->flush();
    }

    private function loginAsAdmin(): string
    {
        $this->client->request(
            'POST',
            '/admin/create-person',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name' => 'Test',
                'surname' => 'Admin',
                'person_type' => 'admin',
                'login' => 'TESTADMIN',
                'password' => 'testadmin123'
            ])
        );

        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => 'TESTADMIN',
                'password' => 'testadmin123'
            ])
        );

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        return $data['token'];
    }

    public function testGetPersonTypes(): void
    {
        $this->client->request(
            'GET',
            '/admin/person-types',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertCount(3, $response);

        $expectedTypes = ['student', 'teacher', 'admin'];
        foreach ($response as $type) {
            $this->assertArrayHasKey('value', $type);
            $this->assertArrayHasKey('label', $type);
            $this->assertContains($type['value'], $expectedTypes);
        }
    }

    public function testGenerateLogin(): void
    {
        $this->client->request(
            'GET',
            '/admin/generate-login',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('login', $response);
        $this->assertArrayHasKey('message', $response);

        $login = $response['login'];
        $this->assertEquals(8, strlen($login));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $login);
    }

    public function testGeneratePassword(): void
    {
        $this->client->request(
            'GET',
            '/admin/generate-password',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('password', $response);
        $this->assertArrayHasKey('message', $response);

        $password = $response['password'];
        $this->assertEquals(8, strlen($password));
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $password);
    }

    public function testGetFaculties(): void
    {
        $this->client->request(
            'GET',
            '/admin/faculties',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(1, count($response));

        $faculty = $response[0];
        $this->assertArrayHasKey('id', $faculty);
        $this->assertArrayHasKey('name', $faculty);
        $this->assertEquals('Test Fakultet', $faculty['name']);
    }

    public function testGetCourses(): void
    {
        $this->client->request(
            'GET',
            '/admin/courses',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($response);
        $this->assertGreaterThanOrEqual(1, count($response));

        $course = $response[0];
        $this->assertArrayHasKey('id', $course);
        $this->assertArrayHasKey('number', $course);
        $this->assertEquals(1, $course['number']);
    }

    public function testGetFacultyGroups(): void
    {
        $faculty = $this->facultyRepository->findOneBy(['name' => 'Test Fakultet']);

        $this->client->request(
            'GET',
            '/admin/faculty/' . $faculty->getId() . '/groups',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('faculty', $response);
        $this->assertArrayHasKey('course_filter', $response);
        $this->assertArrayHasKey('groups', $response);

        $this->assertEquals('Test Fakultet', $response['faculty']['name']);
        $this->assertEquals('all', $response['course_filter']);
        $this->assertIsArray($response['groups']);
    }

    public function testCreateTeacher(): void
    {
        $faculty = $this->facultyRepository->findOneBy(['name' => 'Test Fakultet']);

        $teacherData = [
            'name' => 'Test',
            'surname' => 'Teacher',
            'address' => 'Test Address',
            'phone' => '+998901234567',
            'person_type' => 'teacher',
            'login' => 'TCHR1234',
            'password' => 'teacher123',
            'faculty_id' => $faculty->getId()
        ];

        $this->client->request(
            'POST',
            '/admin/create-person',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken
            ],
            json_encode($teacherData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('person', $response);
        $this->assertArrayHasKey('teacher', $response);

        $this->assertEquals('TCHR1234', $response['person']['login']);
        $this->assertEquals('teacher', $response['person']['person_type']);
    }

    public function testCreateStudent(): void
    {
        $faculty = $this->facultyRepository->findOneBy(['name' => 'Test Fakultet']);
        $course = $this->courseRepository->findOneBy(['number' => 1]);
        $group = $this->groupRepository->findOneBy(['groupNumber' => 101]);

        $studentData = [
            'name' => 'Test',
            'surname' => 'Student',
            'address' => 'Student Address',
            'phone' => '+998901234568',
            'person_type' => 'student',
            'login' => 'STUD1234',
            'password' => 'student123',
            'faculty_id' => $faculty->getId(),
            'course_id' => $course->getId(),
            'group_id' => $group->getId()
        ];

        $this->client->request(
            'POST',
            '/admin/create-person',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken
            ],
            json_encode($studentData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('STUD1234', $response['person']['login']);
        $this->assertEquals('student', $response['person']['person_type']);
        $this->assertArrayHasKey('student', $response);
    }

    public function testCreatePersonValidationErrors(): void
    {
        $invalidData = [
            'name' => '',
            'surname' => 'Test',
            'person_type' => 'invalid_type',
            'login' => '123',
            'password' => 'abc'
        ];

        $this->client->request(
            'POST',
            '/admin/create-person',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken
            ],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('errors', $response);
        $this->assertIsArray($response['errors']);
        $this->assertNotEmpty($response['errors']);
    }

    public function testGetPerson(): void
    {
        $faculty = $this->facultyRepository->findOneBy(['name' => 'Test Fakultet']);

        $this->client->request(
            'POST',
            '/admin/create-person',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken
            ],
            json_encode([
                'name' => 'Get',
                'surname' => 'Person',
                'person_type' => 'teacher',
                'login' => 'GETP1234',
                'password' => 'getperson123',
                'faculty_id' => $faculty->getId()
            ])
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $personId = $createResponse['person']['id'];

        $this->client->request(
            'GET',
            '/admin/person/' . $personId,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseIsSuccessful();

        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($personId, $response['id']);
        $this->assertEquals('GETP1234', $response['login']);
        $this->assertEquals('Get', $response['name']);
        $this->assertEquals('Person', $response['surname']);
        $this->assertEquals('teacher', $response['person_type']);
    }

    public function testGetPersonNotFound(): void
    {
        $this->client->request(
            'GET',
            '/admin/person/999999',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminToken]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->client->request('GET', '/admin/person-types');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->em->createQuery('DELETE FROM App\Entity\Person p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Faculty f')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Course c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Group g')->execute();

        $this->em->flush();
    }
}
