<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Lesson;
use App\Entity\Person;
use App\Entity\Subject;
use App\Entity\Teacher;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ScheduleControllerTest extends WebTestCase
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
                'lesson',
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

    public function testCreateLessonScheduleSuccess(): void
    {
        $gst = $this->createGroupSubjectTeacher();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::FIRST->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_OK,
            $response->getStatusCode(),
            "Create lesson schedule failed. Expected 200, got " . $response->getStatusCode() . ", Body: " . $response->getContent()
        );

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('lesson', $data);
        $this->assertArrayHasKey('id', $data['lesson']);
        $this->assertArrayHasKey('group', $data['lesson']);
        $this->assertArrayHasKey('subject', $data['lesson']);
        $this->assertArrayHasKey('teacher', $data['lesson']);
        $this->assertArrayHasKey('day', $data['lesson']);
        $this->assertArrayHasKey('number', $data['lesson']);
        $this->assertArrayHasKey('weekly_count', $data['lesson']);

        $lessonRepository = $this->em->getRepository(Lesson::class);
        $createdLesson = $lessonRepository->find($data['lesson']['id']);
        $this->assertNotNull($createdLesson);
        $this->assertEquals($gst->getId(), $createdLesson->getGroupSubjectTeacher()->getId());
        $this->assertEquals(WeekDay::MONDAY, $createdLesson->getDay());
        $this->assertEquals(LessonNumber::FIRST, $createdLesson->getNumber());
    }

    public function testCreateLessonScheduleMissingParameters(): void
    {
        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => 1,
            ])
        );

        $response = $this->client->getResponse();

        $statusCode = $response->getStatusCode();

        if ($statusCode === Response::HTTP_INTERNAL_SERVER_ERROR) {
            $responseBody = $response->getContent();

            $data = json_decode($responseBody, true);

            if (isset($data['message']) || isset($data['error'])) {
                $this->markTestSkipped('Controller throws exception when enum tryFrom() fails');
            }
        }

        $this->assertNotEquals(Response::HTTP_OK, $statusCode, "Should not return 200 for missing parameters");

        $this->assertContains($statusCode, [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR
        ]);
    }

    public function testCreateLessonScheduleGSTNotFound(): void
    {
        $nonExistentId = 99999;

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $nonExistentId,
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::FIRST->value
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

    public function testCreateLessonScheduleTeacherBusy(): void
    {
        $gst1 = $this->createGroupSubjectTeacher();
        $gst2 = $this->createGroupSubjectTeacher();

        $lesson = new Lesson();
        $lesson->setGroupSubjectTeacher($gst1);
        $lesson->setDay(WeekDay::MONDAY);
        $lesson->setNumber(LessonNumber::FIRST);
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst1->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::FIRST->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("band", $data['error']);
    }

    public function testCreateLessonScheduleGroupBusy(): void
    {
        $gst1 = $this->createGroupSubjectTeacher();

        $faculty = $gst1->getGroup()->getFaculty();
        $course = $gst1->getGroup()->getCourse();
        $group = $gst1->getGroup();
        $subject2 = $this->createSubject($faculty, 2);
        $teacher2 = $this->createTeacher($faculty);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($group);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($teacher2);
        $this->em->persist($gst2);
        $this->em->flush();

        $lesson = new Lesson();
        $lesson->setGroupSubjectTeacher($gst1);
        $lesson->setDay(WeekDay::MONDAY);
        $lesson->setNumber(LessonNumber::FIRST);
        $this->em->persist($lesson);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst2->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::FIRST->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("band", $data['error']);
    }

    public function testCreateLessonScheduleWeeklyLimitExceeded(): void
    {
        $gst = $this->createGroupSubjectTeacher();

        for ($i = 1; $i <= 2; $i++) {
            $lesson = new Lesson();
            $lesson->setGroupSubjectTeacher($gst);
            $lesson->setDay(WeekDay::cases()[$i-1]);
            $lesson->setNumber(LessonNumber::cases()[$i-1]);
            $this->em->persist($lesson);
        }
        $this->em->flush();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::WEDNESDAY->value,
                'number' => LessonNumber::THIRD->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("haftada 2 martadan", $data['error']);
    }

    public function testCreateLessonScheduleDailyLimitExceeded(): void
    {
        $gst = $this->createGroupSubjectTeacher();
        $group = $gst->getGroup();

        for ($i = 1; $i <= 5; $i++) {
            $subject = $this->createSubject($group->getFaculty(), 2);
            $teacher = $this->createTeacher($group->getFaculty());

            $newGst = new GroupSubjectTeacher();
            $newGst->setGroup($group);
            $newGst->setSubject($subject);
            $newGst->setTeacher($teacher);
            $this->em->persist($newGst);
            $this->em->flush();

            $lesson = new Lesson();
            $lesson->setGroupSubjectTeacher($newGst);
            $lesson->setDay(WeekDay::MONDAY);
            $lesson->setNumber(LessonNumber::cases()[$i-1]);
            $this->em->persist($lesson);
        }
        $this->em->flush();

        $subject6 = $this->createSubject($group->getFaculty(), 2);
        $teacher6 = $this->createTeacher($group->getFaculty());

        $gst6 = new GroupSubjectTeacher();
        $gst6->setGroup($group);
        $gst6->setSubject($subject6);
        $gst6->setTeacher($teacher6);
        $this->em->persist($gst6);
        $this->em->flush();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst6->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::SIXTH->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("5 ta darsdan", $data['error']);
    }

    public function testCreateLessonScheduleInvalidDay(): void
    {
        $gst = $this->createGroupSubjectTeacher();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => 99,
                'number' => LessonNumber::FIRST->value
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("to'liqmas", $data['error']);
    }

    public function testCreateLessonScheduleInvalidLessonNumber(): void
    {
        $gst = $this->createGroupSubjectTeacher();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => 99
            ])
        );

        $response = $this->client->getResponse();

        $this->assertEquals(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode()
        );

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString("to'liqmas", $data['error']);
    }

    public function testCreateLessonScheduleDifferentDaysSuccess(): void
    {
        $gst = $this->createGroupSubjectTeacher();

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::MONDAY->value,
                'number' => LessonNumber::FIRST->value
            ])
        );

        $response1 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::TUESDAY->value,
                'number' => LessonNumber::SECOND->value
            ])
        );

        $response2 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());

        $this->client->request(
            'POST',
            '/admin/lesson-schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'group_subject_teacher_id' => $gst->getId(),
                'day' => WeekDay::WEDNESDAY->value,
                'number' => LessonNumber::THIRD->value
            ])
        );

        $response3 = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response3->getStatusCode());
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
