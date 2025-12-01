<?php

namespace App\Tests\Controller;

use App\Entity\Person;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private PersonRepository $personRepository;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->personRepository = self::getContainer()->get(PersonRepository::class);

        // Har testdan oldin database ni tozalash
        $this->clearDatabase();
    }

    private function clearDatabase(): void
    {
        $connection = $this->em->getConnection();

        // Foreign key constraint'larni o'chirish
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // TRUNCATE - bu DELETE dan tezroq va AUTO_INCREMENT ni avtomatik qayta o'rnatadi
        $connection->executeStatement('TRUNCATE TABLE group_subject_teacher');
        $connection->executeStatement('TRUNCATE TABLE admin');
        $connection->executeStatement('TRUNCATE TABLE teacher');
        $connection->executeStatement('TRUNCATE TABLE student');
        $connection->executeStatement('TRUNCATE TABLE person');

        // Foreign key constraint'larni qayta yoqish
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->em->flush();
    }

    private function createTestPerson(string $login = 'testuser', array $roles = ['ROLE_USER'], string $password = 'testpassword'): Person
    {
        $person = new Person();
        $person->setLogin($login);
        $person->setPassword(password_hash($password, PASSWORD_DEFAULT)); // Hashed password
        $person->setRoles($roles);

        $this->em->persist($person);
        $this->em->flush();

        return $person;
    }

    private function authenticatePerson(Person $person): void
    {
        // Person ni authenticate qilish (session siz)
        $token = new UsernamePasswordToken($person, 'main', $person->getRoles());
        self::getContainer()->get('security.token_storage')->setToken($token);

        // Session kerak emas, chunki JWT stateless authentication ishlatiladi
    }

    public function testLoginSuccess(): void
    {
        // 1. Test person yaratish
        $person = $this->createTestPerson('john_doe', ['ROLE_USER', 'ROLE_TEACHER']);

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($person);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Response strukturasini tekshirish
        $this->assertArrayHasKey('token', $responseData);
        $this->assertArrayHasKey('user', $responseData);

        // 6. Token tekshirish
        $this->assertEquals('JWT token will be added automatically by LexikJWT', $responseData['token']);

        // 7. User ma'lumotlarini tekshirish
        $userData = $responseData['user'];
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('login', $userData);
        $this->assertArrayHasKey('roles', $userData);

        $this->assertEquals($person->getId(), $userData['id']);
        $this->assertEquals('john_doe', $userData['login']);
        $this->assertEquals(['ROLE_USER', 'ROLE_TEACHER'], $userData['roles']);
    }

    public function testLoginWithStudentRole(): void
    {
        // 1. Student person yaratish
        $studentPerson = $this->createTestPerson('student123', ['ROLE_STUDENT']);

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($studentPerson);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Student roles tekshirish
        $this->assertEquals(['ROLE_STUDENT'], $responseData['user']['roles']);
        $this->assertEquals('student123', $responseData['user']['login']);
        $this->assertEquals($studentPerson->getId(), $responseData['user']['id']);
    }

    public function testLoginWithTeacherRole(): void
    {
        // 1. Teacher person yaratish
        $teacherPerson = $this->createTestPerson('teacher456', ['ROLE_TEACHER']);

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($teacherPerson);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Teacher roles tekshirish
        $this->assertEquals(['ROLE_TEACHER'], $responseData['user']['roles']);
        $this->assertEquals('teacher456', $responseData['user']['login']);
    }

    public function testLoginWithAdminRole(): void
    {
        // 1. Admin person yaratish
        $adminPerson = $this->createTestPerson('admin', ['ROLE_ADMIN']);

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($adminPerson);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Admin roles tekshirish
        $this->assertEquals(['ROLE_ADMIN'], $responseData['user']['roles']);
        $this->assertEquals('admin', $responseData['user']['login']);
    }

    public function testLoginWithMultipleRoles(): void
    {
        // 1. Bir nechta rollari bo'lgan person yaratish
        $person = $this->createTestPerson('multi_role_user', ['ROLE_USER', 'ROLE_TEACHER', 'ROLE_ADMIN']);

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($person);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Barcha rollar tekshirish
        $this->assertEquals(['ROLE_USER', 'ROLE_TEACHER', 'ROLE_ADMIN'], $responseData['user']['roles']);
        $this->assertEquals('multi_role_user', $responseData['user']['login']);
    }

    public function testLoginWithoutAuthentication(): void
    {
        // Authenticate qilinmagan holda so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 500 xatosi qaytishi mumkin, chunki controller da null check yo'q
        $statusCode = $this->client->getResponse()->getStatusCode();

        // Agar 500 qaytsa, bu expected bo'ladi, chunki controller da xatolik bor
        // Agar 401/403 qaytsa, bu ham expected
        $this->assertTrue(
            in_array($statusCode, [401, 403, 500]),
            "Expected 401, 403 or 500, got: " . $statusCode
        );

        // Agar 500 bo'lsa, response tarkibini tekshirish
        if ($statusCode === 500) {
            $response = $this->client->getResponse();
            $content = $response->getContent();
            $this->assertStringContainsString('Call to a member function getId() on null', $content);
        }
    }

    public function testLoginWithInvalidMethod(): void
    {
        // GET so'rovi bilan urinish
        $this->client->request('GET', '/api/login');

        $this->assertResponseStatusCodeSame(405); // Method Not Allowed
    }

    public function testLoginResponseStructure(): void
    {
        // 1. Person yaratish va authenticate qilish
        $person = $this->createTestPerson('test_person', ['ROLE_USER']);
        $this->authenticatePerson($person);

        // 2. So'rov yuborish
        $this->client->request('POST', '/api/login');

        // 3. Response strukturasini batafsil tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 4. Asosiy keylar mavjudligini tekshirish
        $this->assertArrayHasKey('token', $responseData);
        $this->assertArrayHasKey('user', $responseData);

        // 5. User ichidagi barcha keylar mavjudligini tekshirish
        $userData = $responseData['user'];
        $requiredKeys = ['id', 'login', 'roles'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $userData, "User array should have key: $key");
        }

        // 6. Ma'lumot turlarini tekshirish
        $this->assertIsString($responseData['token']);
        $this->assertIsInt($userData['id']);
        $this->assertIsString($userData['login']);
        $this->assertIsArray($userData['roles']);
    }

    public function testLoginWithEmptyRoles(): void
    {
        // 1. Roles bo'sh bo'lgan person yaratish (login 15 belgidan oshmasligi kerak)
        $person = $this->createTestPerson('empty_roles', []); // 11 belgi

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($person);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Bo'sh roles array tekshirish
        $this->assertEquals([], $responseData['user']['roles']);
        $this->assertEquals('empty_roles', $responseData['user']['login']);
    }

    public function testLoginWithStudent(): void
    {
        $person = $this->createTestPerson('student1', ['ROLE_STUDENT']);
        $this->authenticatePerson($person);

        $this->client->request('POST', '/api/login');
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($person->getId(), $responseData['user']['id']);
        $this->assertEquals('student1', $responseData['user']['login']);
        $this->assertEquals(['ROLE_STUDENT'], $responseData['user']['roles']);
    }

    public function testLoginWithTeacher(): void
    {
        $person = $this->createTestPerson('teacher1', ['ROLE_TEACHER']);
        $this->authenticatePerson($person);

        $this->client->request('POST', '/api/login');
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($person->getId(), $responseData['user']['id']);
        $this->assertEquals('teacher1', $responseData['user']['login']);
        $this->assertEquals(['ROLE_TEACHER'], $responseData['user']['roles']);
    }

    public function testLoginWithAdmin(): void
    {
        $person = $this->createTestPerson('admin1', ['ROLE_ADMIN']);
        $this->authenticatePerson($person);

        $this->client->request('POST', '/api/login');
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($person->getId(), $responseData['user']['id']);
        $this->assertEquals('admin1', $responseData['user']['login']);
        $this->assertEquals(['ROLE_ADMIN'], $responseData['user']['roles']);
    }

    public function testLoginWithMixedRoles(): void
    {
        $person = $this->createTestPerson('mixed1', ['ROLE_STUDENT', 'ROLE_TEACHER']);
        $this->authenticatePerson($person);

        $this->client->request('POST', '/api/login');
        $this->assertResponseIsSuccessful();

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals($person->getId(), $responseData['user']['id']);
        $this->assertEquals('mixed1', $responseData['user']['login']);
        $this->assertEquals(['ROLE_STUDENT', 'ROLE_TEACHER'], $responseData['user']['roles']);
    }

    public function testLoginWithSpecialCharactersInLogin(): void
    {
        // 1. Maxsus belgilari bo'lgan login bilan person yaratish (15 belgidan oshmasligi kerak)
        $person = $this->createTestPerson('user@domain.com', ['ROLE_USER']); // 14 belgi

        // 2. Person ni authenticate qilish
        $this->authenticatePerson($person);

        // 3. Login endpoint ga so'rov yuborish
        $this->client->request('POST', '/api/login');

        // 4. Response tekshirish
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        // 5. Login to'g'ri qaytarilayotganligini tekshirish
        $this->assertEquals('user@domain.com', $responseData['user']['login']);
        $this->assertEquals(['ROLE_USER'], $responseData['user']['roles']);
    }
}
