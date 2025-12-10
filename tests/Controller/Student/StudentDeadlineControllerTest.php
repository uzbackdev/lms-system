<?php

namespace App\Tests\Controller\Student;

use App\Entity\Person;
use App\Entity\Student;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Faculty;
use App\Entity\Teacher;
use App\Entity\Subject;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Semester;
use App\Entity\Deadline;
use App\Entity\DeadlineSubmission;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StudentDeadlineControllerTest extends WebTestCase
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
    private $semester;
    private $deadline;

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
            'deadline_submission',
            'deadline',
            'lesson',
            'group_subject_teacher',
            'student',
            'teacher',
            'subject',
            'person',
            'student_group',
            'course',
            'faculty',
            'semester',
            'holiday'
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

        $this->semester = new Semester();
        $this->semester->setName('Fall 2024');
        $this->semester->setStartDate(new \DateTime('2024-09-01'));
        $this->semester->setEndDate(new \DateTime('2024-12-31'));
        $this->semester->setIsActive(true);
        $this->em->persist($this->semester);

        $this->gst = new GroupSubjectTeacher();
        $this->gst->setGroup($this->group);
        $this->gst->setSubject($this->subject);
        $this->gst->setTeacher($this->teacher);
        $this->em->persist($this->gst);

        $this->deadline = new Deadline();
        $this->deadline->setGroupSubjectTeacher($this->gst);
        $this->deadline->setSemester($this->semester);
        $this->deadline->setTitle('Test Homework');
        $this->deadline->setDescription('Complete all exercises');
        $this->deadline->setFileName('homework.pdf');
        $this->deadline->setOriginalFileName('homework_original.pdf');
        $this->deadline->setMaxPoints(100);

        $futureDate = new \DateTime('+10 days');
        $this->deadline->setDeadlineDate($futureDate);

        $this->em->persist($this->deadline);

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

    public function testStudentCanGetDeadlines(): void
    {
        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
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

        $studentData = $responseData['student'];

        $this->assertEquals($this->student->getId(), $studentData['id']);
        $this->assertEquals('Test', $studentData['name']);
        $this->assertEquals('Student', $studentData['surname']);
        $this->assertEquals(101, $studentData['group']);

        $this->assertArrayHasKey('deadlines', $responseData);
        $deadlines = $responseData['deadlines'];

        $this->assertIsArray($deadlines);
        $this->assertCount(1, $deadlines);

        $firstDeadline = $deadlines[0];

        $this->assertEquals($this->deadline->getId(), $firstDeadline['id']);
        $this->assertEquals('Test Homework', $firstDeadline['title']);
        $this->assertEquals('Complete all exercises', $firstDeadline['description']);
        $this->assertEquals(100, $firstDeadline['max_points']);
        $this->assertEquals('homework_original.pdf', $firstDeadline['file_name']);
        $this->assertEquals('Mathematics', $firstDeadline['subject_name']);
        $this->assertEquals('John Doe', $firstDeadline['teacher_name']);

        $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{4}$/', $firstDeadline['deadline_date']);
        $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/', $firstDeadline['created_at']);

        $this->assertArrayHasKey('days_remaining', $firstDeadline);
        $this->assertArrayHasKey('can_submit', $firstDeadline);
        $this->assertArrayHasKey('submission_status', $firstDeadline);
        $this->assertArrayHasKey('submission_data', $firstDeadline);

        $this->assertTrue($firstDeadline['can_submit']);
        $this->assertGreaterThan(0, $firstDeadline['days_remaining']);

        $this->assertEquals('not_submitted', $firstDeadline['submission_status']);
        $this->assertNull($firstDeadline['submission_data']);
    }

    public function testDeadlineWithSubmission(): void
    {
        $submission = new DeadlineSubmission();
        $submission->setDeadline($this->deadline);
        $submission->setStudent($this->student);
        $submission->setFileName('submission.pdf');
        $submission->setOriginalFileName('my_homework.pdf');
        $submission->setPoints(85);
        $submission->setStatus('graded');
        $submission->setTeacherComment('Good work!');
        $submission->setGradedAt(new \DateTime());

        $this->em->persist($submission);
        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];
        $firstDeadline = $deadlines[0];

        $this->assertEquals('submitted', $firstDeadline['submission_status']);
        $this->assertNotNull($firstDeadline['submission_data']);

        $submissionData = $firstDeadline['submission_data'];
        $this->assertArrayHasKey('submitted_at', $submissionData);
        $this->assertArrayHasKey('file_name', $submissionData);
        $this->assertArrayHasKey('points', $submissionData);
        $this->assertArrayHasKey('status', $submissionData);
        $this->assertArrayHasKey('teacher_comment', $submissionData);

        $this->assertEquals('my_homework.pdf', $submissionData['file_name']);
        $this->assertEquals(85, $submissionData['points']);
        $this->assertEquals('graded', $submissionData['status']);
        $this->assertEquals('Good work!', $submissionData['teacher_comment']);
    }

    public function testExpiredDeadline(): void
    {
        $pastDeadline = new Deadline();
        $pastDeadline->setGroupSubjectTeacher($this->gst);
        $pastDeadline->setSemester($this->semester);
        $pastDeadline->setTitle('Expired Homework');
        $pastDeadline->setDescription('This deadline has passed');
        $pastDeadline->setMaxPoints(50);

        $pastDate = new \DateTime('-5 days');
        $pastDeadline->setDeadlineDate($pastDate);

        $this->em->persist($pastDeadline);
        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertCount(2, $deadlines);

        $expiredDeadline = null;
        foreach ($deadlines as $deadline) {
            if ($deadline['title'] === 'Expired Homework') {
                $expiredDeadline = $deadline;
                break;
            }
        }

        $this->assertNotNull($expiredDeadline);

        $this->assertFalse($expiredDeadline['can_submit']);
        $this->assertLessThan(0, $expiredDeadline['days_remaining']);
    }

    public function testMultipleDeadlinesDifferentSubjects(): void
    {
        $faculty = $this->em->getRepository(Faculty::class)->findOneBy(['name' => 'Test Faculty']);

        $subject2 = new Subject();
        $subject2->setSubjectName('Physics');
        $subject2->setCourseNumber(1);
        $subject2->setFaculty($faculty);
        $this->em->persist($subject2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($this->group);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $deadline2 = new Deadline();
        $deadline2->setGroupSubjectTeacher($gst2);
        $deadline2->setSemester($this->semester);
        $deadline2->setTitle('Physics Lab Report');
        $deadline2->setDescription('Write lab report for experiment');
        $deadline2->setMaxPoints(80);

        $futureDate2 = new \DateTime('+7 days');
        $deadline2->setDeadlineDate($futureDate2);

        $this->em->persist($deadline2);
        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertCount(2, $deadlines);

        $subjectNames = array_map(function($deadline) {
            return $deadline['subject_name'];
        }, $deadlines);

        $this->assertContains('Mathematics', $subjectNames);
        $this->assertContains('Physics', $subjectNames);

        $titles = array_map(function($deadline) {
            return $deadline['title'];
        }, $deadlines);

        $this->assertContains('Test Homework', $titles);
        $this->assertContains('Physics Lab Report', $titles);
    }

    public function testDeadlineWithoutFile(): void
    {
        $deadlineWithoutFile = new Deadline();
        $deadlineWithoutFile->setGroupSubjectTeacher($this->gst);
        $deadlineWithoutFile->setSemester($this->semester);
        $deadlineWithoutFile->setTitle('Oral Exam');
        $deadlineWithoutFile->setDescription('Prepare for oral examination');
        $deadlineWithoutFile->setMaxPoints(30);
        $deadlineWithoutFile->setFileName(null);
        $deadlineWithoutFile->setOriginalFileName(null);

        $futureDate = new \DateTime('+3 days');
        $deadlineWithoutFile->setDeadlineDate($futureDate);

        $this->em->persist($deadlineWithoutFile);
        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $oralExamDeadline = null;
        foreach ($deadlines as $deadline) {
            if ($deadline['title'] === 'Oral Exam') {
                $oralExamDeadline = $deadline;
                break;
            }
        }

        $this->assertNotNull($oralExamDeadline);
        $this->assertNull($oralExamDeadline['file_name']);
    }

    public function testSubmissionWithDifferentStatuses(): void
    {
        $submission = new DeadlineSubmission();
        $submission->setDeadline($this->deadline);
        $submission->setStudent($this->student);
        $submission->setFileName('submission.pdf');
        $submission->setOriginalFileName('my_homework.pdf');
        $submission->setStatus('submitted');

        $this->em->persist($submission);
        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];
        $firstDeadline = $deadlines[0];

        $this->assertEquals('submitted', $firstDeadline['submission_status']);

        $submissionData = $firstDeadline['submission_data'];
        $this->assertEquals('submitted', $submissionData['status']);
        $this->assertNull($submissionData['points']);
        $this->assertNull($submissionData['teacher_comment']);
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
            '/student/deadlines',
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
        $this->client->request('GET', '/student/deadlines');

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
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testDifferentGroupStudentGetsNoDeadlines(): void
    {
        $faculty = $this->em->getRepository(Faculty::class)->findOneBy(['name' => 'Test Faculty']);
        $course = $this->em->getRepository(Course::class)->findOneBy(['number' => 1]);

        $group2 = new Group();
        $group2->setGroupNumber(102);
        $group2->setCourse($course);
        $group2->setFaculty($faculty);
        $this->em->persist($group2);

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
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertEmpty($deadlines);
    }

    public function testDeadlineOrderByDate(): void
    {
        $this->em->remove($this->deadline);
        $this->em->flush();

        $dates = [
            '+15 days',
            '+3 days',
            '+7 days',
        ];

        foreach ($dates as $dateOffset) {
            $deadline = new Deadline();
            $deadline->setGroupSubjectTeacher($this->gst);
            $deadline->setSemester($this->semester);
            $deadline->setTitle('Deadline ' . $dateOffset);
            $deadline->setDescription('Test ' . $dateOffset);
            $deadline->setMaxPoints(rand(50, 100));

            $date = new \DateTime($dateOffset);
            $deadline->setDeadlineDate($date);

            $this->em->persist($deadline);
        }

        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertCount(3, $deadlines, 'Should have 3 deadlines');

        $daysRemaining = array_map(function($deadline) {
            return $deadline['days_remaining'];
        }, $deadlines);

        $sorted = $daysRemaining;
        sort($sorted);

        $this->assertEquals($sorted, $daysRemaining, 'Deadlines should be sorted by days remaining in ascending order');

        $expectedOrder = [3, 7, 15];
        $actualOrder = [];

        foreach ($deadlines as $deadline) {
            if (strpos($deadline['title'], '+3 days') !== false) {
                $actualOrder[] = 3;
            } elseif (strpos($deadline['title'], '+7 days') !== false) {
                $actualOrder[] = 7;
            } elseif (strpos($deadline['title'], '+15 days') !== false) {
                $actualOrder[] = 15;
            }
        }

        $this->assertEquals($expectedOrder, $actualOrder, 'Deadlines should be in correct date order: +3 days, +7 days, +15 days');
    }

    public function testEmptyDeadlinesWhenNoneExist(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('DELETE FROM deadline_submission');
        $connection->executeStatement('DELETE FROM deadline');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertIsArray($deadlines);
        $this->assertEmpty($deadlines);
    }

    public function testDeadlineOrderingWithMixedDates(): void
    {
        $deadlinesToCreate = [
            ['offset' => '+20 days', 'title' => 'Latest Deadline'],
            ['offset' => '+2 days', 'title' => 'Earliest Deadline'],
            ['offset' => '+10 days', 'title' => 'Middle Deadline'],
            ['offset' => '+5 days', 'title' => 'Early Deadline'],
            ['offset' => '+15 days', 'title' => 'Late Deadline'],
        ];

        $this->em->remove($this->deadline);
        $this->em->flush();

        foreach ($deadlinesToCreate as $deadlineInfo) {
            $deadline = new Deadline();
            $deadline->setGroupSubjectTeacher($this->gst);
            $deadline->setSemester($this->semester);
            $deadline->setTitle($deadlineInfo['title']);
            $deadline->setDescription('Description for ' . $deadlineInfo['title']);
            $deadline->setMaxPoints(rand(50, 100));

            $date = new \DateTime($deadlineInfo['offset']);
            $deadline->setDeadlineDate($date);

            $this->em->persist($deadline);
        }

        $this->em->flush();

        $token = $this->loginAndGetToken($this->studentUser, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/student/deadlines',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]
        );

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $deadlines = $responseData['deadlines'];

        $this->assertCount(5, $deadlines);

        $previousDaysRemaining = null;
        foreach ($deadlines as $deadline) {
            $currentDaysRemaining = $deadline['days_remaining'];

            if ($previousDaysRemaining !== null) {
                $this->assertGreaterThanOrEqual(
                    $previousDaysRemaining,
                    $currentDaysRemaining,
                    "Deadlines should be ordered by days remaining: previous {$previousDaysRemaining}, current {$currentDaysRemaining}"
                );
            }
            $previousDaysRemaining = $currentDaysRemaining;
        }

        $titles = array_map(function($deadline) {
            return $deadline['title'];
        }, $deadlines);

        $expectedOrder = ['Earliest Deadline', 'Early Deadline', 'Middle Deadline', 'Late Deadline', 'Latest Deadline'];
        $this->assertEquals($expectedOrder, $titles, 'Deadlines should be in correct chronological order');
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
        $this->semester = null;
        $this->deadline = null;
    }
}
