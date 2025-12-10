<?php

namespace App\Tests\Controller\Student;

use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Lesson;
use App\Entity\Person;
use App\Entity\Student;
use App\Entity\Subject;
use App\Entity\Teacher;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StudentScheduleControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $hasher;
    private $studentUser;
    private $student;
    private $group;
    private $teacher;
    private $subject;
    private $gst;
    private $lesson;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->hasher = $container->get('security.user_password_hasher');

        $this->cleanDatabase();

        $this->createTestData();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'lesson',
            'group_subject_teacher',
            'student',
            'teacher',
            'subject',
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

        $this->group = new Group();
        $this->group->setGroupNumber(101);
        $this->group->setCourse($course);
        $this->group->setFaculty($faculty);
        $this->em->persist($this->group);

        $teacherPerson = new Person();
        $teacherPerson->setLogin('tch_' . rand(100, 999));
        $teacherPerson->setPassword($this->hasher->hashPassword($teacherPerson, 'teacher123'));
        $teacherPerson->setRoles(['ROLE_TEACHER']);
        $this->em->persist($teacherPerson);

        $this->teacher = new Teacher();
        $this->teacher->setName('John');
        $this->teacher->setSurname('Doe');
        $this->teacher->setFaculty($faculty);
        $this->teacher->setPerson($teacherPerson);
        $teacherPerson->setTeacher($this->teacher);
        $this->em->persist($this->teacher);

        $this->subject = new Subject();
        $this->subject->setSubjectName('Mathematics');
        $this->subject->setCourseNumber(1);
        $this->subject->setFaculty($faculty);
        $this->em->persist($this->subject);

        $this->gst = new GroupSubjectTeacher();
        $this->gst->setGroup($this->group);
        $this->gst->setSubject($this->subject);
        $this->gst->setTeacher($this->teacher);
        $this->em->persist($this->gst);

        $this->lesson = new Lesson();
        $this->lesson->setGroupSubjectTeacher($this->gst);
        $this->lesson->setNumber(LessonNumber::FIRST);
        $this->lesson->setDay(WeekDay::MONDAY);
        $this->em->persist($this->lesson);

        $this->studentUser = new Person();
        $this->studentUser->setLogin('stu_' . rand(100, 999));
        $this->studentUser->setPassword($this->hasher->hashPassword($this->studentUser, 'password123'));
        $this->studentUser->setRoles(['ROLE_STUDENT']);
        $this->em->persist($this->studentUser);

        $this->student = new Student();
        $this->student->setName('Test');
        $this->student->setSurname('Student');
        $this->student->setAddress('Test Address');
        $this->student->setPerson($this->studentUser);
        $this->student->setGroup($this->group);
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

    public function testStudentCanGetCurrentWeekSchedule(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('schedule', $responseData);

        $schedule = $responseData['schedule'];

        $this->assertArrayHasKey('monday', $schedule);
        $this->assertArrayHasKey('tuesday', $schedule);
        $this->assertArrayHasKey('wednesday', $schedule);
        $this->assertArrayHasKey('thursday', $schedule);
        $this->assertArrayHasKey('friday', $schedule);

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            $this->assertArrayHasKey('date', $schedule[$day]);
            $this->assertArrayHasKey('lessons', $schedule[$day]);

            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $schedule[$day]['date']);

            $this->assertCount(6, $schedule[$day]['lessons']);

            for ($i = 1; $i <= 6; $i++) {
                $this->assertArrayHasKey($i, $schedule[$day]['lessons']);
            }
        }

        $this->assertEquals('Mathematics', $schedule['monday']['lessons'][1]);

        $this->assertNull($schedule['tuesday']['lessons'][1]);
    }

    public function testStudentCanGetSubjectsAndTeachers(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('student', $responseData);
        $studentData = $responseData['student'];

        $this->assertEquals($this->student->getId(), $studentData['id']);
        $this->assertEquals('Test', $studentData['name']);
        $this->assertEquals('Student', $studentData['surname']);
        $this->assertEquals(101, $studentData['group']);
        $this->assertEquals(1, $studentData['course']);

        $this->assertArrayHasKey('subjects_teachers', $responseData);
        $subjectsTeachers = $responseData['subjects_teachers'];

        $this->assertIsArray($subjectsTeachers);
        $this->assertCount(1, $subjectsTeachers);

        $firstItem = $subjectsTeachers[0];

        $this->assertArrayHasKey('id', $firstItem);
        $this->assertEquals($this->gst->getId(), $firstItem['id']);

        $this->assertArrayHasKey('subject', $firstItem);
        $subjectData = $firstItem['subject'];

        $this->assertEquals($this->subject->getId(), $subjectData['id']);
        $this->assertEquals('Mathematics', $subjectData['name']);
        $this->assertEquals(1, $subjectData['course_number']);

        $this->assertArrayHasKey('teacher', $firstItem);
        $teacherData = $firstItem['teacher'];

        $this->assertEquals($this->teacher->getId(), $teacherData['id']);
        $this->assertEquals('John', $teacherData['name']);
        $this->assertEquals('Doe', $teacherData['surname']);
        $this->assertEquals('Test Faculty', $teacherData['faculty']);

        $this->assertArrayHasKey('group', $firstItem);
        $groupData = $firstItem['group'];

        $this->assertEquals($this->group->getId(), $groupData['id']);
        $this->assertEquals(101, $groupData['number']);
        $this->assertEquals(1, $groupData['course']);
    }

    public function testMultipleSubjectsAndTeachers(): void
    {
        $faculty = $this->em->getRepository(Faculty::class)->findOneBy(['name' => 'Test Faculty']);

        $subject2 = new Subject();
        $subject2->setSubjectName('Physics');
        $subject2->setCourseNumber(1);
        $subject2->setFaculty($faculty);
        $this->em->persist($subject2);

        $teacherPerson2 = new Person();
        $teacherPerson2->setLogin('tch2_' . rand(100, 999));
        $teacherPerson2->setPassword($this->hasher->hashPassword($teacherPerson2, 'teacher123'));
        $teacherPerson2->setRoles(['ROLE_TEACHER']);
        $this->em->persist($teacherPerson2);

        $teacher2 = new Teacher();
        $teacher2->setName('Jane');
        $teacher2->setSurname('Smith');
        $teacher2->setFaculty($faculty);
        $teacher2->setPerson($teacherPerson2);
        $teacherPerson2->setTeacher($teacher2);
        $this->em->persist($teacher2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($this->group);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($teacher2);
        $this->em->persist($gst2);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $subjectsTeachers = $responseData['subjects_teachers'];

        $this->assertCount(2, $subjectsTeachers);

        $subjectNames = array_map(function($item) {
            return $item['subject']['name'];
        }, $subjectsTeachers);

        $this->assertContains('Mathematics', $subjectNames);
        $this->assertContains('Physics', $subjectNames);

        $teacherNames = array_map(function($item) {
            return $item['teacher']['name'] . ' ' . $item['teacher']['surname'];
        }, $subjectsTeachers);

        $this->assertContains('John Doe', $teacherNames);
        $this->assertContains('Jane Smith', $teacherNames);
    }

    public function testScheduleWithMultipleLessons(): void
    {
        $lesson2 = new Lesson();
        $lesson2->setGroupSubjectTeacher($this->gst);
        $lesson2->setNumber(LessonNumber::SECOND);
        $lesson2->setDay(WeekDay::MONDAY);
        $this->em->persist($lesson2);

        $lesson3 = new Lesson();
        $lesson3->setGroupSubjectTeacher($this->gst);
        $lesson3->setNumber(LessonNumber::FIRST);
        $lesson3->setDay(WeekDay::TUESDAY);
        $this->em->persist($lesson3);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $this->assertEquals('Mathematics', $schedule['monday']['lessons'][1]);
        $this->assertEquals('Mathematics', $schedule['monday']['lessons'][2]);

        $this->assertEquals('Mathematics', $schedule['tuesday']['lessons'][1]);

        $this->assertNull($schedule['monday']['lessons'][3]);
        $this->assertNull($schedule['tuesday']['lessons'][2]);
    }

    public function testStudentNotFoundReturns404(): void
    {
        $personWithoutStudent = new Person();
        $personWithoutStudent->setLogin('no_stu_' . rand(100, 999));
        $personWithoutStudent->setPassword($this->hasher->hashPassword($personWithoutStudent, 'password123'));
        $personWithoutStudent->setRoles(['ROLE_STUDENT']);
        $this->em->persist($personWithoutStudent);
        $this->em->flush();

        $token = $this->loginAndGetToken($personWithoutStudent, 'password123');

        if (!$token) {
            $this->fail('Login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Student topilmadi', $responseData['error']);

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Student topilmadi', $responseData['error']);
    }

    public function testUnauthenticatedAccessFails(): void
    {
        $this->client->request('GET', '/student/schedule');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', '/student/my-subjects-teachers');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testNonStudentCannotAccess(): void
    {
        $teacherPerson = $this->teacher->getPerson();
        $token = $this->loginAndGetToken($teacherPerson, 'teacher123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testAdminCannotAccessStudentEndpoint(): void
    {
        $adminPerson = new Person();
        $adminPerson->setLogin('adm_' . rand(100, 999));
        $adminPerson->setPassword($this->hasher->hashPassword($adminPerson, 'admin123'));
        $adminPerson->setRoles(['ROLE_ADMIN']);
        $this->em->persist($adminPerson);
        $this->em->flush();

        $token = $this->loginAndGetToken($adminPerson, 'admin123');

        if (!$token) {
            $this->fail('Admin login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testEmptyScheduleWhenNoLessons(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('DELETE FROM lesson');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            for ($i = 1; $i <= 6; $i++) {
                $this->assertNull($schedule[$day]['lessons'][$i]);
            }
        }
    }

    public function testEmptySubjectsTeachersWhenNoGST(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('DELETE FROM lesson');
        $connection->executeStatement('DELETE FROM group_subject_teacher');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $subjectsTeachers = $responseData['subjects_teachers'];

        $this->assertIsArray($subjectsTeachers);
        $this->assertEmpty($subjectsTeachers);
    }

    public function testDifferentGroupStudentGetsDifferentData(): void
    {
        $faculty = $this->em->getRepository(Faculty::class)->findOneBy(['name' => 'Test Faculty']);
        $course = $this->em->getRepository(Course::class)->findOneBy(['number' => 1]);

        $group2 = new Group();
        $group2->setGroupNumber(102);
        $group2->setCourse($course);
        $group2->setFaculty($faculty);
        $this->em->persist($group2);

        $subject2 = new Subject();
        $subject2->setSubjectName('Chemistry');
        $subject2->setCourseNumber(1);
        $subject2->setFaculty($faculty);
        $this->em->persist($subject2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($group2);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $studentPerson2 = new Person();
        $studentPerson2->setLogin('stu2_' . rand(100, 999));
        $studentPerson2->setPassword($this->hasher->hashPassword($studentPerson2, 'password123'));
        $studentPerson2->setRoles(['ROLE_STUDENT']);
        $this->em->persist($studentPerson2);

        $student2 = new Student();
        $student2->setName('Second');
        $student2->setSurname('Student');
        $student2->setAddress('Second Address');
        $student2->setPerson($studentPerson2);
        $student2->setGroup($group2);
        $studentPerson2->setStudent($student2);
        $this->em->persist($student2);

        $this->em->flush();

        $token = $this->loginAndGetToken($studentPerson2, 'password123');

        if (!$token) {
            $this->fail('Second student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/my-subjects-teachers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $subjectsTeachers = $responseData['subjects_teachers'];

        $this->assertCount(1, $subjectsTeachers);
        $this->assertEquals('Chemistry', $subjectsTeachers[0]['subject']['name']);
        $this->assertEquals(102, $subjectsTeachers[0]['group']['number']);
    }

    public function testScheduleWeekDatesAreCorrect(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $expectedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        foreach ($expectedDays as $day) {
            $this->assertArrayHasKey($day, $schedule);

            $date = $schedule[$day]['date'];
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);

            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            $this->assertInstanceOf(\DateTime::class, $dateTime);
            $this->assertEquals($date, $dateTime->format('Y-m-d'));
        }

        $dates = [];
        foreach ($expectedDays as $day) {
            $dates[] = new \DateTime($schedule[$day]['date']);
        }

        for ($i = 1; $i < count($dates); $i++) {
            $diff = $dates[$i]->diff($dates[$i - 1]);
            $this->assertEquals(1, $diff->days, "{$expectedDays[$i-1]} dan {$expectedDays[$i]} ga 1 kun farq bo'lishi kerak");
        }
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
        $this->group = null;
        $this->teacher = null;
        $this->subject = null;
        $this->gst = null;
        $this->lesson = null;
    }
}
