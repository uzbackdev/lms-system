<?php

namespace App\Tests\Controller\Teacher;

use App\Entity\Person;
use App\Entity\Teacher;
use App\Entity\Faculty;
use App\Entity\Subject;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Semester;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Deadline;
use App\Entity\Student;
use App\Entity\DeadlineSubmission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TeacherGradingControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Teacher $teacher;
    private GroupSubjectTeacher $gst;
    private Semester $activeSemester;
    private Deadline $deadline;
    private Student $student;
    private DeadlineSubmission $submission;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->clearDatabase();
        $this->createTestData();
    }

    private function clearDatabase(): void
    {
        $tables = [
            'deadline_submission',
            'deadline',
            'student',
            'group_subject_teacher',
            'lesson',
            'holiday',
            'semester',
            'teacher_subject',
            'teacher',
            'admin',
            'person',
            'student_group',
            'subject',
            'course',
            'faculty'
        ];

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

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
        $group->setFaculty($faculty);
        $group->setCourse($course);
        $group->setGroupNumber(101);
        $this->em->persist($group);

        $subject = new Subject();
        $subject->setSubjectName('Test Subject');
        $subject->setFaculty($faculty);
        $subject->setCourseNumber(1);
        $subject->setIsCommonFirstYear(false);
        $this->em->persist($subject);

        $teacherPerson = new Person();
        $teacherPerson->setLogin('t.teacher');
        $teacherPerson->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $teacherPerson->setRoles(['ROLE_TEACHER']);
        $this->em->persist($teacherPerson);

        $this->teacher = new Teacher();
        $this->teacher->setName('Test');
        $this->teacher->setSurname('Teacher');
        $this->teacher->setFaculty($faculty);
        $this->teacher->setPerson($teacherPerson);

        $reflection = new \ReflectionClass($this->teacher);
        $subjectsProperty = $reflection->getProperty('subjects');
        $subjectsProperty->setAccessible(true);
        $subjectsProperty->setValue($this->teacher, new ArrayCollection());

        $this->em->persist($this->teacher);
        $this->teacher->getSubjects()->add($subject);

        $subjectReflection = new \ReflectionClass($subject);
        $teachersProperty = $subjectReflection->getProperty('teachers');
        $teachersProperty->setAccessible(true);
        $teachersProperty->setValue($subject, new ArrayCollection());
        $subject->getTeachers()->add($this->teacher);

        $studentPerson = new Person();
        $studentPerson->setLogin('student1');
        $studentPerson->setPassword(password_hash('password123', PASSWORD_DEFAULT));
        $studentPerson->setRoles(['ROLE_STUDENT']);
        $this->em->persist($studentPerson);

        $this->student = new Student();
        $this->student->setName('Test');
        $this->student->setSurname('Student');
        $this->student->setGroup($group);
        $this->student->setPerson($studentPerson);
        $this->student->setAddress("Yunusabad");
        $this->em->persist($this->student);

        $this->activeSemester = new Semester();
        $this->activeSemester->setName('Fall 2024');
        $this->activeSemester->setStartDate(new \DateTime('-1 month'));
        $this->activeSemester->setEndDate(new \DateTime('+1 month'));
        $this->activeSemester->setIsActive(true);
        $this->em->persist($this->activeSemester);

        $this->gst = new GroupSubjectTeacher();
        $this->gst->setGroup($group);
        $this->gst->setSubject($subject);
        $this->gst->setTeacher($this->teacher);
        $this->em->persist($this->gst);

        $this->deadline = new Deadline();
        $this->deadline->setGroupSubjectTeacher($this->gst);
        $this->deadline->setSemester($this->activeSemester);
        $this->deadline->setTitle('Test Deadline');
        $this->deadline->setDescription('Test description');
        $this->deadline->setMaxPoints(10);
        $this->deadline->setDeadlineDate(new \DateTime('+1 week'));
        $this->em->persist($this->deadline);

        $this->submission = new DeadlineSubmission();
        $this->submission->setDeadline($this->deadline);
        $this->submission->setStudent($this->student);
        $this->submission->setFileName('test_file.pdf');
        $this->submission->setOriginalFileName('test_document.pdf');
        $this->submission->setStatus('submitted');
        $this->submission->setSubmittedAt(new \DateTime());
        $this->em->persist($this->submission);

        $this->em->flush();
    }

    private function getJwtToken(): string
    {
        $this->client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => 't.teacher',
                'password' => 'password123'
            ])
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== 200) {
            $this->markTestSkipped('Authentication failed');
        }

        $data = json_decode($response->getContent(), true);

        if (!isset($data['token'])) {
            $this->markTestSkipped('Token not received');
        }

        return $data['token'];
    }

    public function testCreateGrade(): void
    {
        $jwtToken = $this->getJwtToken();

        $submissionId = $this->submission->getId();
        $requestData = [
            'points' => 8,
            'teacher_comment' => 'Yaxshi ish!'
        ];

        $this->client->request(
            'POST',
            "/teacher/submissions/{$submissionId}/grade",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Baho qoʻyildi', $data['message']);
    }

    public function testUpdateGrade(): void
    {
        $jwtToken = $this->getJwtToken();

        $submissionId = $this->submission->getId();
        $this->submission->setPoints(8);
        $this->submission->setStatus('graded');
        $this->em->flush();

        $requestData = [
            'points' => 9,
            'teacher_comment' => 'Yanada yaxshiroq!'
        ];

        $this->client->request(
            'PUT',
            "/teacher/submissions/{$submissionId}/grade",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Baho yangilandi', $data['message']);
    }

    public function testCreateGradeWithMissingPoints(): void
    {
        $jwtToken = $this->getJwtToken();

        $submissionId = $this->submission->getId();
        $requestData = [
            'teacher_comment' => 'Yaxshi ish!'
        ];

        $this->client->request(
            'POST',
            "/teacher/submissions/{$submissionId}/grade",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCreateGradeWithInvalidSubmission(): void
    {
        $jwtToken = $this->getJwtToken();

        $requestData = [
            'points' => 8,
            'teacher_comment' => 'Yaxshi ish!'
        ];

        $this->client->request(
            'POST',
            "/teacher/submissions/9999/grade",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetGSTGradingMatrixWithInvalidGst(): void
    {
        $jwtToken = $this->getJwtToken();

        $this->client->request(
            'GET',
            "/teacher/gst/9999/grading-matrix",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUnauthorizedAccess(): void
    {
        $submissionId = $this->submission->getId();
        $requestData = [
            'points' => 8,
            'teacher_comment' => 'Yaxshi ish!'
        ];

        $this->client->request(
            'POST',
            "/teacher/submissions/{$submissionId}/grade",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
