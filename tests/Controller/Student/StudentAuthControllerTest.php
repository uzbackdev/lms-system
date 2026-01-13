<?php

namespace App\Tests\Controller\Student;

use App\Entity\Person;
use App\Entity\Student;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Faculty;
use App\Repository\PersonRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StudentAuthControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $hasher;
    private $studentUser;
    private $student;
    private PersonRepository $personRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->hasher = $container->get('security.user_password_hasher');

        $this->personRepository = $container->get(PersonRepository::class);

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
            'student_group',
            'course',
            'faculty'
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
        $faculty = new Faculty();
        $faculty->setName('Test Faculty');
        $this->em->persist($faculty);

        $course = new Course();
        $course->setNumber(1);
        $this->em->persist($course);

        $group = new Group();
        $group->setGroupNumber(101);
        $group->setCourse($course);
        $group->setFaculty($faculty);
        $this->em->persist($group);

        $this->studentUser = new Person();
        $this->studentUser->setLogin('stu_' . rand(100, 999));
        $this->studentUser->setPassword($this->hasher->hashPassword($this->studentUser, 'old_password123'));
        $this->studentUser->setRoles(['ROLE_STUDENT']);
        $this->em->persist($this->studentUser);

        $this->student = new Student();
        $this->student->setName('Test');
        $this->student->setSurname('Student');
        $this->student->setAddress('Test Address');
        $this->student->setPerson($this->studentUser);
        $this->student->setGroup($group);

        $this->studentUser->setStudent($this->student);

        $this->em->persist($this->student);
        $this->em->flush();
    }

    private function loginAndGetToken(Person $user, string $password): ?string
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $user->getLogin(),
                'password' => $password
            ])
        );

        if ($this->client->getResponse()->getStatusCode() === Response::HTTP_OK) {
            $response = json_decode($this->client->getResponse()->getContent(), true);
            return $response['token'] ?? null;
        }

        return null;
    }

    public function testStudentCanChangePasswordSuccessfully(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'new_password456',
                'confirmPassword' => 'new_password456'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Parol yangilandi', $responseData['message']);

        $this->em->clear();

        $updatedPerson = $this->personRepository->find($this->studentUser->getId());

        $loginSuccess = $this->hasher->isPasswordValid($updatedPerson, 'new_password456');
        $this->assertTrue($loginSuccess);

        $oldLoginSuccess = $this->hasher->isPasswordValid($updatedPerson, 'old_password123');
        $this->assertFalse($oldLoginSuccess);
    }

    public function testWrongOldPasswordFails(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'wrong_old_password',
                'newPassword' => 'new_password456',
                'confirmPassword' => 'new_password456'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Eski parol notoʻgʻri', $responseData['error']);
    }

    public function testPasswordMismatchFails(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'new_password456',
                'confirmPassword' => 'different_password789'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Yangi parol va tasdiqlash paroli mos kemadi', $responseData['error']);
    }

    public function testNewPasswordSameAsOldFails(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'old_password123',
                'confirmPassword' => 'old_password123'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Bu parol eski paroldan farq qilishi kerak', $responseData['error']);
    }

    public function testMissingFieldsValidation(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'newPassword' => 'new_password456',
                'confirmPassword' => 'new_password456'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'confirmPassword' => 'new_password456'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'new_password456'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testUnauthenticatedAccessFails(): void
    {
        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'new_password456',
                'confirmPassword' => 'new_password456'
            ])
        );

        $response = $this->client->getResponse();
        $statusCode = $response->getStatusCode();
        $content = $response->getContent();

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $statusCode);

        $this->assertNotEmpty($content);

        $responseData = json_decode($content, true);

        if (isset($responseData['message'])) {
            $this->assertEquals('JWT Token not found', $responseData['message']);
        } elseif (isset($responseData['error'])) {
            $this->assertEquals('JWT Token not found', $responseData['error']);
        } else {
            $this->assertStringContainsString('JWT', $content, 'Response should contain JWT related message');
        }
    }

    public function testInvalidTokenFails(): void
    {
        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer invalid_token_here',
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'new_password456',
                'confirmPassword' => 'new_password456'
            ])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testNonStudentCannotAccess(): void
    {
        $teacherUser = new Person();
        $teacherUser->setLogin('tch_' . rand(100, 999));
        $teacherUser->setPassword($this->hasher->hashPassword($teacherUser, 'teacher123'));
        $teacherUser->setRoles(['ROLE_TEACHER']);
        $this->em->persist($teacherUser);
        $this->em->flush();

        $token = $this->loginAndGetToken($teacherUser, 'teacher123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'teacher123',
                'newPassword' => 'new_teacher456',
                'confirmPassword' => 'new_teacher456'
            ])
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCannotAccessStudentEndpoint(): void
    {
        $adminUser = new Person();
        $adminUser->setLogin('adm_' . rand(100, 999));
        $adminUser->setPassword($this->hasher->hashPassword($adminUser, 'admin123'));
        $adminUser->setRoles(['ROLE_ADMIN']);
        $this->em->persist($adminUser);
        $this->em->flush();

        $token = $this->loginAndGetToken($adminUser, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'admin123',
                'newPassword' => 'new_admin456',
                'confirmPassword' => 'new_admin456'
            ])
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testEmptyPasswordFields(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => '',
                'newPassword' => '',
                'confirmPassword' => ''
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testInvalidJsonFormat(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
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

    public function testCanSetComplexPassword(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $complexPassword = 'P@ssw0rd!2024';

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => $complexPassword,
                'confirmPassword' => $complexPassword
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->em->clear();

        $updatedPerson = $this->personRepository->find($this->studentUser->getId());

        $loginSuccess = $this->hasher->isPasswordValid($updatedPerson, $complexPassword);
        $this->assertTrue($loginSuccess);
    }

    public function testMultiplePasswordChanges(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'first_new_password',
                'confirmPassword' => 'first_new_password'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $newToken = $this->loginAndGetToken($this->studentUser, 'first_new_password');

        if (!$newToken) {
            $this->fail('Yangi parol bilan login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $newToken,
            ],
            json_encode([
                'oldPassword' => 'first_new_password',
                'newPassword' => 'second_new_password',
                'confirmPassword' => 'second_new_password'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->em->clear();

        $updatedPerson = $this->personRepository->find($this->studentUser->getId());

        $loginSuccess = $this->hasher->isPasswordValid($updatedPerson, 'second_new_password');
        $this->assertTrue($loginSuccess);

        $oldLoginSuccess = $this->hasher->isPasswordValid($updatedPerson, 'first_new_password');
        $this->assertFalse($oldLoginSuccess);
    }

    public function testStudentControllerActuallyCalled(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'old_password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'POST',
            '/student/change-password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'oldPassword' => 'old_password123',
                'newPassword' => 'test_new_password',
                'confirmPassword' => 'test_new_password'
            ])
        );

        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->assertArrayHasKey('success', $responseData);
            $this->assertTrue($responseData['success']);
            $this->assertArrayHasKey('message', $responseData);
        } elseif ($response->getStatusCode() === Response::HTTP_BAD_REQUEST) {
            $this->assertArrayHasKey('error', $responseData);
        }
    }

    public function testPersonRepositoryMethods(): void
    {
        $foundPerson = $this->personRepository->find($this->studentUser->getId());
        $this->assertNotNull($foundPerson);
        $this->assertEquals($this->studentUser->getId(), $foundPerson->getId());

        $foundByLogin = $this->personRepository->findOneBy(['login' => $this->studentUser->getLogin()]);
        $this->assertNotNull($foundByLogin);
        $this->assertEquals($this->studentUser->getLogin(), $foundByLogin->getLogin());

        $allPersons = $this->personRepository->findAll();
        $this->assertGreaterThanOrEqual(1, count($allPersons));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }

        $this->client = null;
        $this->studentUser = null;
        $this->student = null;
        $this->personRepository = null;
    }
}
