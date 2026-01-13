<?php

namespace App\Tests\Controller\Teacher;

use App\Entity\Person;
use App\Entity\Teacher;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Subject;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Lesson;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TeacherScheduleControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $hasher;
    private $teacherUser;
    private $teacher;
    private $faculty;
    private $course;
    private $group;
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
            'deadline_submission',
            'deadline',
            'group_subject_teacher',
            'student',
            'teacher_subject',
            'teacher',
            'person',
            'student_group',
            'subject',
            'faculty',
            'course',
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

    private function generateLogin(string $prefix): string
    {
        $random = rand(10, 99);
        $login = $prefix . $random;

        if (strlen($login) > 15) {
            $login = substr($login, 0, 15);
        }

        return $login;
    }

    private function createTestData(): void
    {
        $this->faculty = new Faculty();
        $this->faculty->setName('Computer Science');
        $this->em->persist($this->faculty);

        $this->course = new Course();
        $this->course->setNumber(1);
        $this->em->persist($this->course);

        $this->group = new Group();
        $this->group->setGroupNumber(101);
        $this->group->setCourse($this->course);
        $this->group->setFaculty($this->faculty);
        $this->em->persist($this->group);

        $this->teacherUser = new Person();
        $this->teacherUser->setLogin($this->generateLogin('tch_'));
        $this->teacherUser->setPassword($this->hasher->hashPassword($this->teacherUser, 'password123'));
        $this->teacherUser->setRoles(['ROLE_TEACHER']);
        $this->em->persist($this->teacherUser);

        $this->teacher = new Teacher();
        $this->teacher->setName('John');
        $this->teacher->setSurname('Doe');
        $this->teacher->setFaculty($this->faculty);

        $this->teacher->setPerson($this->teacherUser);
        $this->teacherUser->setTeacher($this->teacher);

        $this->em->persist($this->teacher);

        $this->subject = new Subject();
        $this->subject->setSubjectName('Mathematics');
        $this->subject->setCourseNumber(1);
        $this->subject->setFaculty($this->faculty);
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

        $this->em->flush();

        $teacherUserId = $this->teacherUser->getId();
        $facultyId = $this->faculty->getId();
        $courseId = $this->course->getId();
        $groupId = $this->group->getId();
        $subjectId = $this->subject->getId();
        $gstId = $this->gst->getId();

        $this->em->clear();

        $this->teacherUser = $this->em->getRepository(Person::class)->find($teacherUserId);
        $this->teacher = $this->teacherUser->getTeacher();
        $this->faculty = $this->em->getRepository(Faculty::class)->find($facultyId);
        $this->course = $this->em->getRepository(Course::class)->find($courseId);
        $this->group = $this->em->getRepository(Group::class)->find($groupId);
        $this->subject = $this->em->getRepository(Subject::class)->find($subjectId);
        $this->gst = $this->em->getRepository(GroupSubjectTeacher::class)->find($gstId);
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

    public function testTeacherCanGetWeeklySchedule(): void
    {
        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);

        $this->assertArrayHasKey('schedule', $responseData);
        $schedule = $responseData['schedule'];

        $this->assertArrayHasKey('monday', $schedule);
        $mondaySchedule = $schedule['monday'];

        $this->assertArrayHasKey('date', $mondaySchedule);
        $this->assertArrayHasKey('lessons', $mondaySchedule);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $mondaySchedule['date']);

        $lessons = $mondaySchedule['lessons'];
        $this->assertIsArray($lessons);

        $this->assertCount(6, $lessons);

        $this->assertNotNull($lessons[1]);
        $this->assertEquals('Mathematics', $lessons[1]['subject']);
        $this->assertEquals(101, $lessons[1]['group']);

        $this->assertNull($lessons[2]);
        $this->assertNull($lessons[3]);
        $this->assertNull($lessons[4]);
        $this->assertNull($lessons[5]);
        $this->assertNull($lessons[6]);
    }

    public function testTeacherScheduleWithMultipleLessons(): void
    {
        $lesson2 = new Lesson();
        $lesson2->setGroupSubjectTeacher($this->gst);
        $lesson2->setNumber(LessonNumber::THIRD);
        $lesson2->setDay(WeekDay::TUESDAY);
        $this->em->persist($lesson2);

        $lesson3 = new Lesson();
        $lesson3->setGroupSubjectTeacher($this->gst);
        $lesson3->setNumber(LessonNumber::FIFTH);
        $lesson3->setDay(WeekDay::WEDNESDAY);
        $this->em->persist($lesson3);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $mondayLessons = $schedule['monday']['lessons'];
        $this->assertNotNull($mondayLessons[1]);
        $this->assertEquals('Mathematics', $mondayLessons[1]['subject']);
        $this->assertEquals(101, $mondayLessons[1]['group']);

        $tuesdayLessons = $schedule['tuesday']['lessons'];
        $this->assertNotNull($tuesdayLessons[3]);
        $this->assertEquals('Mathematics', $tuesdayLessons[3]['subject']);
        $this->assertEquals(101, $tuesdayLessons[3]['group']);

        $wednesdayLessons = $schedule['wednesday']['lessons'];
        $this->assertNotNull($wednesdayLessons[5]);
        $this->assertEquals('Mathematics', $wednesdayLessons[5]['subject']);
        $this->assertEquals(101, $wednesdayLessons[5]['group']);

        $thursdayLessons = $schedule['thursday']['lessons'];
        $fridayLessons = $schedule['friday']['lessons'];

        $this->assertNull($thursdayLessons[1]);
        $this->assertNull($thursdayLessons[2]);
        $this->assertNull($thursdayLessons[3]);
        $this->assertNull($thursdayLessons[4]);
        $this->assertNull($thursdayLessons[5]);
        $this->assertNull($thursdayLessons[6]);

        $this->assertNull($fridayLessons[1]);
        $this->assertNull($fridayLessons[2]);
        $this->assertNull($fridayLessons[3]);
        $this->assertNull($fridayLessons[4]);
        $this->assertNull($fridayLessons[5]);
        $this->assertNull($fridayLessons[6]);
    }

    public function testTeacherScheduleWithDifferentSubjects(): void
    {
        $subject2 = new Subject();
        $subject2->setSubjectName('Physics');
        $subject2->setCourseNumber(1);
        $subject2->setFaculty($this->faculty);
        $this->em->persist($subject2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($this->group);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $physicsLesson = new Lesson();
        $physicsLesson->setGroupSubjectTeacher($gst2);
        $physicsLesson->setNumber(LessonNumber::SECOND);
        $physicsLesson->setDay(WeekDay::MONDAY);
        $this->em->persist($physicsLesson);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $mondayLessons = $schedule['monday']['lessons'];

        $this->assertNotNull($mondayLessons[1]);
        $this->assertEquals('Mathematics', $mondayLessons[1]['subject']);
        $this->assertEquals(101, $mondayLessons[1]['group']);

        $this->assertNotNull($mondayLessons[2]);
        $this->assertEquals('Physics', $mondayLessons[2]['subject']);
        $this->assertEquals(101, $mondayLessons[2]['group']);
    }

    public function testTeacherScheduleWithDifferentGroups(): void
    {
        $group2 = new Group();
        $group2->setGroupNumber(102);

        $course = $this->em->getRepository(Course::class)->findOneBy(['number' => 1]);
        $group2->setCourse($course);
        $group2->setFaculty($this->faculty);
        $this->em->persist($group2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($group2);
        $gst2->setSubject($this->subject);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $lesson2 = new Lesson();
        $lesson2->setGroupSubjectTeacher($gst2);
        $lesson2->setNumber(LessonNumber::THIRD);
        $lesson2->setDay(WeekDay::TUESDAY);
        $this->em->persist($lesson2);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $mondayLessons = $schedule['monday']['lessons'];
        $this->assertNotNull($mondayLessons[1]);
        $this->assertEquals('Mathematics', $mondayLessons[1]['subject']);
        $this->assertEquals(101, $mondayLessons[1]['group']);

        $tuesdayLessons = $schedule['tuesday']['lessons'];
        $this->assertNotNull($tuesdayLessons[3]);
        $this->assertEquals('Mathematics', $tuesdayLessons[3]['subject']);
        $this->assertEquals(102, $tuesdayLessons[3]['group']);
    }

    public function testTeacherWithoutLessons(): void
    {
        $newTeacherPerson = new Person();
        $newTeacherPerson->setLogin($this->generateLogin('tch2_'));
        $newTeacherPerson->setPassword($this->hasher->hashPassword($newTeacherPerson, 'password123'));
        $newTeacherPerson->setRoles(['ROLE_TEACHER']);
        $this->em->persist($newTeacherPerson);

        $newTeacher = new Teacher();
        $newTeacher->setName('Jane');
        $newTeacher->setSurname('Smith');

        $faculty = $this->em->getRepository(Faculty::class)->findOneBy(['name' => 'Computer Science']);
        $newTeacher->setFaculty($faculty);

        $newTeacher->setPerson($newTeacherPerson);
        $newTeacherPerson->setTeacher($newTeacher);

        $this->em->persist($newTeacher);
        $this->em->flush();

        $token = $this->loginAndGetToken($newTeacherPerson, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $this->assertIsArray($schedule);

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        foreach ($days as $day) {
            $this->assertArrayHasKey($day, $schedule);
            $this->assertArrayHasKey('date', $schedule[$day]);
            $this->assertArrayHasKey('lessons', $schedule[$day]);

            $lessons = $schedule[$day]['lessons'];
            for ($i = 1; $i <= 6; $i++) {
                $this->assertNull($lessons[$i]);
            }
        }
    }

    public function testTeacherNotFoundReturns404(): void
    {
        $personWithoutTeacher = new Person();
        $personWithoutTeacher->setLogin($this->generateLogin('noteach_'));
        $personWithoutTeacher->setPassword($this->hasher->hashPassword($personWithoutTeacher, 'password123'));
        $personWithoutTeacher->setRoles(['ROLE_TEACHER']);
        $this->em->persist($personWithoutTeacher);
        $this->em->flush();

        $token = $this->loginAndGetToken($personWithoutTeacher, 'password123');

        if (!$token) {
            $this->fail('Login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Oʻqituvchi topilmadi', $responseData['error']);
    }

    public function testUnauthenticatedAccessFails(): void
    {
        $this->client->request('GET', '/teacher/schedule');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testNonTeacherCannotAccess(): void
    {
        $studentPerson = new Person();
        $studentPerson->setLogin($this->generateLogin('stu_'));
        $studentPerson->setPassword($this->hasher->hashPassword($studentPerson, 'password123'));
        $studentPerson->setRoles(['ROLE_STUDENT']);
        $this->em->persist($studentPerson);
        $this->em->flush();

        $token = $this->loginAndGetToken($studentPerson, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testScheduleCurrentWeekDates(): void
    {
        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        foreach ($days as $day) {
            $this->assertArrayHasKey($day, $schedule);
            $this->assertArrayHasKey('date', $schedule[$day]);

            $date = $schedule[$day]['date'];
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);

            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            $this->assertNotFalse($dateTime, "Invalid date format for {$day}: {$date}");
        }

        $dates = [];
        foreach ($days as $day) {
            $dates[$day] = $schedule[$day]['date'];
        }

        $mondayDate = new \DateTime($dates['monday']);
        $tuesdayDate = new \DateTime($dates['tuesday']);
        $wednesdayDate = new \DateTime($dates['wednesday']);
        $thursdayDate = new \DateTime($dates['thursday']);
        $fridayDate = new \DateTime($dates['friday']);

        $this->assertEquals('Monday', $mondayDate->format('l'));
        $this->assertEquals('Tuesday', $tuesdayDate->format('l'));
        $this->assertEquals('Wednesday', $wednesdayDate->format('l'));
        $this->assertEquals('Thursday', $thursdayDate->format('l'));
        $this->assertEquals('Friday', $fridayDate->format('l'));

        $expectedTuesday = clone $mondayDate;
        $expectedTuesday->modify('+1 day');
        $this->assertEquals($expectedTuesday->format('Y-m-d'), $tuesdayDate->format('Y-m-d'));

        $expectedWednesday = clone $tuesdayDate;
        $expectedWednesday->modify('+1 day');
        $this->assertEquals($expectedWednesday->format('Y-m-d'), $wednesdayDate->format('Y-m-d'));

        $expectedThursday = clone $wednesdayDate;
        $expectedThursday->modify('+1 day');
        $this->assertEquals($expectedThursday->format('Y-m-d'), $thursdayDate->format('Y-m-d'));

        $expectedFriday = clone $thursdayDate;
        $expectedFriday->modify('+1 day');
        $this->assertEquals($expectedFriday->format('Y-m-d'), $fridayDate->format('Y-m-d'));
    }

    public function testScheduleWithFullWeek(): void
    {
        $lessonsData = [
            ['day' => WeekDay::MONDAY, 'number' => LessonNumber::FIRST],
            ['day' => WeekDay::MONDAY, 'number' => LessonNumber::THIRD],
            ['day' => WeekDay::TUESDAY, 'number' => LessonNumber::SECOND],
            ['day' => WeekDay::TUESDAY, 'number' => LessonNumber::FOURTH],
            ['day' => WeekDay::WEDNESDAY, 'number' => LessonNumber::FIFTH],
            ['day' => WeekDay::THURSDAY, 'number' => LessonNumber::SIXTH],
            ['day' => WeekDay::FRIDAY, 'number' => LessonNumber::FIRST],
            ['day' => WeekDay::FRIDAY, 'number' => LessonNumber::THIRD],
        ];

        foreach ($lessonsData as $lessonData) {
            $lesson = new Lesson();
            $lesson->setGroupSubjectTeacher($this->gst);
            $lesson->setNumber($lessonData['number']);
            $lesson->setDay($lessonData['day']);
            $this->em->persist($lesson);
        }

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/schedule',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $schedule = $responseData['schedule'];

        $expectedLessons = [
            'monday' => [1, 3],
            'tuesday' => [2, 4],
            'wednesday' => [5],
            'thursday' => [6],
            'friday' => [1, 3],
        ];

        foreach ($expectedLessons as $day => $lessonNumbers) {
            $dayLessons = $schedule[$day]['lessons'];

            foreach ($lessonNumbers as $lessonNumber) {
                $this->assertNotNull($dayLessons[$lessonNumber]);
                $this->assertEquals('Mathematics', $dayLessons[$lessonNumber]['subject']);
                $this->assertEquals(101, $dayLessons[$lessonNumber]['group']);
            }

            for ($i = 1; $i <= 6; $i++) {
                if (!in_array($i, $lessonNumbers)) {
                    $this->assertNull($dayLessons[$i]);
                }
            }
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
        $this->teacherUser = null;
        $this->teacher = null;
        $this->faculty = null;
        $this->course = null;
        $this->group = null;
        $this->subject = null;
        $this->gst = null;
        $this->lesson = null;
    }
}
