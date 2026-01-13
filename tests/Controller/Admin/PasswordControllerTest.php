<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Person;
use App\Entity\Teacher;
use App\Entity\Student;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Faculty;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class PasswordControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $hasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->hasher = $container->get('security.user_password_hasher');

        $this->cleanDatabase();
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

    private function createAdmin(): Person
    {
        $admin = new Person();

        $admin->setLogin('adm_' . rand(100, 999));
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);

        $this->em->persist($admin);
        $this->em->flush();

        return $admin;
    }

    private function createFaculty(): Faculty
    {
        $faculty = new Faculty();
        $faculty->setName('Test Faculty');

        $this->em->persist($faculty);
        $this->em->flush();

        return $faculty;
    }

    private function createCourse(): Course
    {
        $course = new Course();
        $course->setNumber(1);

        $this->em->persist($course);
        $this->em->flush();

        return $course;
    }

    private function createGroup(Course $course, Faculty $faculty): Group
    {
        $group = new Group();
        $group->setGroupNumber(101);
        $group->setCourse($course);
        $group->setFaculty($faculty);

        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    private function createTeacherWithPerson(): Teacher
    {
        $faculty = $this->createFaculty();

        $person = new Person();
        $person->setLogin('tch_' . rand(100, 999));
        $person->setPassword($this->hasher->hashPassword($person, 'old_password'));
        $person->setRoles(['ROLE_TEACHER']);

        $teacher = new Teacher();
        $teacher->setName('Test');
        $teacher->setSurname('Teacher');
        $teacher->setFaculty($faculty);
        $teacher->setPerson($person);

        $person->setTeacher($teacher);

        $this->em->persist($faculty);
        $this->em->persist($person);
        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    private function createStudentWithPerson(): Student
    {
        $faculty = $this->createFaculty();
        $course = $this->createCourse();
        $group = $this->createGroup($course, $faculty);

        $person = new Person();
        $person->setLogin('stu_' . rand(100, 999));
        $person->setPassword($this->hasher->hashPassword($person, 'old_password'));
        $person->setRoles(['ROLE_STUDENT']);

        $student = new Student();
        $student->setName('Test');
        $student->setSurname('Student');
        $student->setAddress('Test Address');
        $student->setPerson($person);
        $student->setGroup($group);

        $person->setStudent($student);

        $this->em->persist($faculty);
        $this->em->persist($course);
        $this->em->persist($group);
        $this->em->persist($person);
        $this->em->persist($student);
        $this->em->flush();

        return $student;
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

    public function testAdminCanChangeTeacherPassword(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacherWithPerson();
        $oldPasswordHash = $teacher->getPerson()->getPassword();

        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => $teacher->getId(),
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Parol muvaffaqiyatli yangilandi', $responseData['message']);

        $this->em->clear();
        $updatedTeacher = $this->em->getRepository(Teacher::class)->find($teacher->getId());
        $newPasswordHash = $updatedTeacher->getPerson()->getPassword();

        $this->assertNotEquals($oldPasswordHash, $newPasswordHash);

        $loginSuccess = $this->hasher->isPasswordValid(
            $updatedTeacher->getPerson(),
            'newpassword123'
        );
        $this->assertTrue($loginSuccess);
    }

    public function testAdminCanChangeStudentPassword(): void
    {
        $admin = $this->createAdmin();
        $student = $this->createStudentWithPerson();
        $oldPasswordHash = $student->getPerson()->getPassword();

        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/students/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'student_id' => $student->getId(),
                'new_password' => 'newstudent123'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Student paroli muvaffaqiyatli yangilandi', $responseData['message']);

        $this->em->clear();
        $updatedStudent = $this->em->getRepository(Student::class)->find($student->getId());
        $newPasswordHash = $updatedStudent->getPerson()->getPassword();

        $this->assertNotEquals($oldPasswordHash, $newPasswordHash);

        $loginSuccess = $this->hasher->isPasswordValid(
            $updatedStudent->getPerson(),
            'newstudent123'
        );
        $this->assertTrue($loginSuccess);
    }

    public function testTeacherIdRequired(): void
    {
        $admin = $this->createAdmin();
        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Teacher ID kiritilmagan', $responseData['error']);
    }

    public function testStudentIdRequired(): void
    {
        $admin = $this->createAdmin();
        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/students/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Student ID kiritilmagan', $responseData['error']);
    }

    public function testPasswordTooShort(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacherWithPerson();

        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => $teacher->getId(),
                'new_password' => '123'
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Parol kamida 6 ta belgidan iborat bo\'lishi kerak', $responseData['error']);
    }

    public function testTeacherNotFound(): void
    {
        $admin = $this->createAdmin();
        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => 99999,
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('O\'qituvchi topilmadi', $responseData['error']);
    }

    public function testStudentNotFound(): void
    {
        $admin = $this->createAdmin();
        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/students/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'student_id' => 99999,
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Student topilmadi', $responseData['error']);
    }

    public function testNonAdminCannotAccess(): void
    {
        $teacher = $this->createTeacherWithPerson();

        $token = $this->loginAndGetToken($teacher->getPerson(), 'old_password');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => $teacher->getId(),
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testStudentCannotAccessAdminEndpoint(): void
    {
        $student = $this->createStudentWithPerson();

        $token = $this->loginAndGetToken($student->getPerson(), 'old_password');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/students/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'student_id' => $student->getId(),
                'new_password' => 'newpassword123'
            ])
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testNewPasswordRequired(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacherWithPerson();

        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => $teacher->getId()
            ])
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Yangi parol kiritilmagan', $responseData['error']);
    }

    public function testCanSetPasswordExactly6Characters(): void
    {
        $admin = $this->createAdmin();
        $teacher = $this->createTeacherWithPerson();
        $oldPasswordHash = $teacher->getPerson()->getPassword();

        $token = $this->loginAndGetToken($admin, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'PUT',
            '/admin/teachers/password',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode([
                'teacher_id' => $teacher->getId(),
                'new_password' => '123456'
            ])
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $this->em->clear();
        $updatedTeacher = $this->em->getRepository(Teacher::class)->find($teacher->getId());

        $loginSuccess = $this->hasher->isPasswordValid(
            $updatedTeacher->getPerson(),
            '123456'
        );
        $this->assertTrue($loginSuccess);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }

        $this->client = null;
    }
}
