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

class FourthYearControllerTest extends WebTestCase
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

    public function testGetFourthYearSubjectsByGroupSuccess(): void
    {

        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty);

        $subject = new Subject();
        $subject->setSubjectName('Final Year Project');
        $subject->setCourseNumber(4);
        $subject->setFaculty($faculty);
        $subject->setIsCommonFirstYear(false);

        $this->em->persist($subject);
        $this->em->flush();


        $this->client->request(
            'GET',
            '/admin/group-subject-teacher/subjects/fourth-year/by-group/' . $group->getId(),
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
            "GET request failed. Expected 200, got " . $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success'], "Success false. Response: " . json_encode($data));
        $this->assertIsArray($data['data']);


        if (count($data['data']) > 0) {
            $subjectData = $data['data'][0];
            $this->assertArrayHasKey('id', $subjectData);
            $this->assertArrayHasKey('name', $subjectData);
            $this->assertArrayHasKey('faculty', $subjectData);
            $this->assertArrayHasKey('courseNumber', $subjectData);
        }
    }

    public function testGetFourthYearSubjectsByGroupNotFound(): void
    {
        $nonExistentId = 99999;

        $this->client->request(
            'GET',
            '/admin/group-subject-teacher/subjects/fourth-year/by-group/' . $nonExistentId,
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
            "Expected 404 for non-existent group"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Guruh topilmadi', $data['error']);
    }

    public function testCreateFourthYearSuccess(): void
    {

        $faculty = $this->createFaculty();
        $teacher = $this->createTeacher($faculty);

        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 4);


        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Create failed. Expected 200, got " . $response->getStatusCode() . ", Body: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals("4-kurs uchun to'g'ri biriktirildi", $data['message']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);


        $gstRepository = $this->em->getRepository(GroupSubjectTeacher::class);
        $createdGst = $gstRepository->find($data['data']['id']);
        $this->assertNotNull($createdGst);
        $this->assertEquals($group->getId(), $createdGst->getGroup()->getId());
        $this->assertEquals($subject->getId(), $createdGst->getSubject()->getId());
        $this->assertEquals($teacher->getId(), $createdGst->getTeacher()->getId());
    }

    public function testCreateFourthYearMissingParameters(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => 1,

            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('group_id, subject_id va teacher_id', $data['error']);
    }

    public function testCreateFourthYearNotFound(): void
    {
        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => 99999,
                'subject_id' => 99999,
                'teacher_id' => 99999,
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("topilmadi", $data['error']);
    }

    public function testCreateFourthYearDuplicateGroupSubject(): void
    {

        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 4);
        $teacher = $this->createTeacher($faculty);


        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );


        $firstResponse = $this->client->getResponse();
        $this->assertEquals(
            Response::HTTP_OK,
            $firstResponse->getStatusCode(),
            "First creation should succeed"
        );


        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            "Expected 400 for duplicate group-subject"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("allaqachon", $data['error']);
    }

    public function testCreateFourthYearNotFourthYearGroup(): void
    {

        $faculty = $this->createFaculty();
        $course = $this->getOrCreateCourse(3);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 4);
        $teacher = $this->createTeacher($faculty);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("4-kurslar", $data['error']);
    }

    public function testCreateFourthYearTeacherDifferentFaculty(): void
    {

        $faculty1 = $this->createFaculty();
        $faculty2 = $this->createFaculty();

        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty1);
        $subject = $this->createSubject($faculty1, 4);
        $teacher = $this->createTeacher($faculty2);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            "Should fail when teacher and subject are in different faculties"
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("o'z fakulteti", $data['error']);
    }

    public function testCreateFourthYearTeacherWorkloadExceeded(): void
    {

        $this->markTestSkipped('Teacher workload logic tested in Service layer');
    }

    public function testCreateFourthYearValidSameFaculty(): void
    {
        // O'qituvchi va fan bir fakultetda bo'lishi kerak
        $faculty = $this->createFaculty();
        $teacher = $this->createTeacher($faculty);

        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 4);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();


        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Should succeed when teacher and subject are in same faculty. Response: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }



    public function testCreateFourthYearWithGroupFromDifferentFacultyButSameTeacherFaculty(): void
    {

        $faculty1 = $this->createFaculty();
        $faculty2 = $this->createFaculty();

        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty1);
        $subject = $this->createSubject($faculty2, 4);
        $teacher = $this->createTeacher($faculty2);

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Should succeed because teacher and subject are in same faculty"
        );
    }

    public function testCreateFourthYearWhenTeacherFacultyEqualsSubjectFaculty(): void
    {

        $faculty = $this->createFaculty();
        $teacher = $this->createTeacher($faculty);

        $course = $this->getOrCreateCourse(4);
        $group = $this->createGroup($course, $faculty);
        $subject = $this->createSubject($faculty, 4);


        $this->assertEquals(
            $teacher->getFaculty()->getId(),
            $subject->getFaculty()->getId(),
            "Teacher and Subject should be in same faculty for 4th year"
        );

        $this->client->request(
            'POST',
            '/admin/group-subject-teacher/fourth-year',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_id' => $group->getId(),
                'subject_id' => $subject->getId(),
                'teacher_id' => $teacher->getId(),
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode()
        );
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
        $subject->setIsCommonFirstYear(false);

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


    private function refreshEntities(): void
    {

    }

    protected function tearDown(): void
    {

        if ($this->em->isOpen()) {
            $this->em->clear();
        }

        parent::tearDown();
    }
}
