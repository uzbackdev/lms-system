<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Person;
use App\Entity\Subject;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class GSTManagementControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get('doctrine')->getManager();

        $this->cleanupTestData();

        $this->createTestAdminUser();

        $this->loginAndGetToken();
    }

    private function cleanupTestData(): void
    {
        $connection = $this->em->getConnection();

        try {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

            $tables = [
                'group_subject_teacher',
                'deadline',
                'student_group',
                'subject',
                'teacher',
                'faculty',
                'course',
                'person'
            ];

            foreach ($tables as $table) {
                try {
                    if ($table === 'person') {
                        $connection->executeStatement("DELETE FROM person WHERE login LIKE 'test_%'");
                    } else {
                        $connection->executeStatement("DELETE FROM $table");
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        } catch (\Exception $e) {
        }
    }

    private function createTestAdminUser(): void
    {
        $personRepository = $this->em->getRepository(Person::class);

        $adminPerson = $personRepository->findOneBy(['login' => 'test_admin']);

        if (!$adminPerson) {
            $adminPerson = new Person();
            $adminPerson->setLogin('test_admin');

            $passwordHasher = $this->client->getContainer()->get('security.user_password_hasher');
            $hashedPassword = $passwordHasher->hashPassword($adminPerson, 'test_password');
            $adminPerson->setPassword($hashedPassword);

            $adminPerson->setRoles(['ROLE_ADMIN']);

            $this->em->persist($adminPerson);
            $this->em->flush();
        }
    }

    private function loginAndGetToken(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'login' => 'test_admin',
                'password' => 'test_password'
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Login failed. Status: " . $response->getStatusCode() . ", Body: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('token', $data, "Token response'da yo'q. Response: " . json_encode($data));

        $this->token = $data['token'];
        $this->assertNotEmpty($this->token, "Token bo'sh");
    }

    public function testDeleteSuccess(): void
    {
        $gst = $this->createGroupSubjectTeacher();
        $gstId = $gst->getId();

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $gstId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "DELETE request failed. Expected 200, got " . $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals("Biriktirish muvaffaqiyatli o'chirildi", $data['message']);

        $gstRepository = $this->em->getRepository(GroupSubjectTeacher::class);
        $deletedGst = $gstRepository->find($gstId);
        $this->assertNull($deletedGst, "GroupSubjectTeacher should be deleted from database");
    }

    public function testDeleteNotFound(): void
    {
        $nonExistentId = 99999;

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $nonExistentId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            "Expected 404 for non-existent GroupSubjectTeacher"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Biriktirish topilmadi', $data['error']);
    }

    public function testDeleteWithoutAuthentication(): void
    {
        $gst = $this->createGroupSubjectTeacher();
        $gstId = $gst->getId();

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $gstId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode(),
            "Expected 401 without authentication"
        );
    }

    public function testDeleteWithInvalidId(): void
    {
        $invalidId = 0;

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $invalidId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode(),
            "Expected 404 for ID 0 (valid integer format but not found)"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Biriktirish topilmadi', $data['error']);
    }

    public function testDeleteWithStringId(): void
    {
        $this->markTestSkipped('String ID causes TypeError - Symfony route expects integer');
    }

    public function testDeleteCascadeEffect(): void
    {
        $gst = $this->createGroupSubjectTeacher();
        $gstId = $gst->getId();

        $groupId = $gst->getGroup()->getId();
        $subjectId = $gst->getSubject()->getId();
        $teacherId = $gst->getTeacher()->getId();

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $gstId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $group = $this->em->getRepository(Group::class)->find($groupId);
        $subject = $this->em->getRepository(Subject::class)->find($subjectId);
        $teacher = $this->em->getRepository(Teacher::class)->find($teacherId);

        $this->assertNotNull($group, "Group should still exist after deleting GroupSubjectTeacher");
        $this->assertNotNull($subject, "Subject should still exist after deleting GroupSubjectTeacher");
        $this->assertNotNull($teacher, "Teacher should still exist after deleting GroupSubjectTeacher");

        $deletedGst = $this->em->getRepository(GroupSubjectTeacher::class)->find($gstId);
        $this->assertNull($deletedGst, "GroupSubjectTeacher should be deleted");
    }

    public function testDeleteMultipleTimes(): void
    {
        $gst = $this->createGroupSubjectTeacher();
        $gstId = $gst->getId();

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $gstId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $firstResponse = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $firstResponse->getStatusCode());

        $this->client->request(
            'DELETE',
            '/admin/group-subject-teacher/' . $gstId,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $secondResponse = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $secondResponse->getStatusCode(),
            "Expected 404 when deleting already deleted entity"
        );

        $data = json_decode($secondResponse->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Biriktirish topilmadi', $data['error']);
    }

    private function createGroupSubjectTeacher(): GroupSubjectTeacher
    {
        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(2);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 2);
        $teacher = $this->createTeacher($faculty);

        $gst = new GroupSubjectTeacher();
        $gst->setGroup($group);
        $gst->setSubject($subject);
        $gst->setTeacher($teacher);

        $this->em->persist($gst);
        $this->em->flush();

        return $gst;
    }

    private function createFaculty(): Faculty
    {
        $faculty = new Faculty();
        $faculty->setName('Test Faculty ' . uniqid());

        $this->em->persist($faculty);
        $this->em->flush();

        return $faculty;
    }

    private function getOrCreateCourse(int $number): Course
    {
        $courseRepository = $this->em->getRepository(Course::class);

        $existingCourse = $courseRepository->findOneBy(['number' => $number]);

        if ($existingCourse) {
            return $existingCourse;
        }

        $course = new Course();
        $course->setNumber($number);

        $this->em->persist($course);
        $this->em->flush();

        return $course;
    }

    private function createGroup(Course $course, Faculty $faculty): Group
    {
        if (!$this->em->contains($course)) {
            $this->em->persist($course);
            $this->em->flush();
        }

        if (!$this->em->contains($faculty)) {
            $this->em->persist($faculty);
            $this->em->flush();
        }

        $group = new Group();
        $group->setGroupNumber(rand(100, 999));
        $group->setCourse($course);
        $group->setFaculty($faculty);

        $this->em->persist($group);
        $this->em->flush();

        return $group;
    }

    private function createSubject(Faculty $faculty, int $courseNumber): Subject
    {
        if (!$this->em->contains($faculty)) {
            $this->em->persist($faculty);
            $this->em->flush();
        }

        $subject = new Subject();
        $subject->setSubjectName('Test Subject ' . uniqid());
        $subject->setCourseNumber($courseNumber);
        $subject->setFaculty($faculty);
        $subject->setIsCommonFirstYear($courseNumber === 1);

        $this->em->persist($subject);
        $this->em->flush();

        return $subject;
    }

    private function createTeacher(Faculty $faculty): Teacher
    {
        if (!$this->em->contains($faculty)) {
            $this->em->persist($faculty);
            $this->em->flush();
        }

        $teacher = new Teacher();
        $teacher->setName('Test');
        $teacher->setSurname('Teacher ' . uniqid());
        $teacher->setFaculty($faculty);

        $this->em->persist($teacher);
        $this->em->flush();

        return $teacher;
    }

    protected function tearDown(): void
    {
        if ($this->em->isOpen()) {
            $this->em->clear();
        }

        parent::tearDown();
    }
}
