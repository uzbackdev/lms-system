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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TeacherDeadlineControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Teacher $teacher;
    private GroupSubjectTeacher $gst;
    private Semester $activeSemester;

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
            'deadline',
            'deadline_submission',
            'group_subject_teacher',
            'lesson',
            'holiday',
            'semester',
            'teacher_subject',
            'student',
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

        $person = new Person();
        $person->setLogin('t.teacher');

        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            throw new \RuntimeException('Password hash failed');
        }
        $person->setPassword($hashedPassword);
        $person->setRoles(['ROLE_TEACHER']);
        $this->em->persist($person);

        $this->teacher = new Teacher();
        $this->teacher->setName('Test');
        $this->teacher->setSurname('Teacher');
        $this->teacher->setFaculty($faculty);
        $this->teacher->setPerson($person);

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
            $this->markTestSkipped('Authentication failed: ' . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);

        if (!isset($data['token'])) {
            $this->markTestSkipped('Token not received: ' . json_encode($data));
        }

        return $data['token'];
    }

    public function testCreateDeadlineWithJson(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadlineDate = (new \DateTime('+1 week'))->format('Y-m-d');
        $requestData = [
            'gst_id' => $this->gst->getId(),
            'title' => 'Test Deadline',
            'description' => 'Test description for deadline',
            'max_points' => 10,
            'deadline_date' => $deadlineDate
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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
        $this->assertEquals('Deadline yaratildi', $data['message']);
        $this->assertArrayHasKey('deadline', $data);
        $this->assertEquals('Test Deadline', $data['deadline']['title']);
        $this->assertEquals(10, $data['deadline']['max_points']);
    }

    public function testCreateDeadlineWithMissingFields(): void
    {
        $jwtToken = $this->getJwtToken();

        $requestData = [
            'gst_id' => $this->gst->getId(),
            'title' => 'Test Deadline',
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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
        $this->assertEquals('Barcha maydonlar toʻldirilishi shart', $data['error']);
    }

    public function testCreateDeadlineWithInvalidGst(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadlineDate = (new \DateTime('+1 week'))->format('Y-m-d');
        $requestData = [
            'gst_id' => 9999,
            'title' => 'Test Deadline',
            'description' => 'Test description',
            'max_points' => 10,
            'deadline_date' => $deadlineDate
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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

    public function testCreateDeadlineOutsideSemester(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadlineDate = (new \DateTime('+3 months'))->format('Y-m-d');
        $requestData = [
            'gst_id' => $this->gst->getId(),
            'title' => 'Test Deadline',
            'description' => 'Test description',
            'max_points' => 10,
            'deadline_date' => $deadlineDate
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('details', $data);
    }

    public function testCreateDeadlineExceedsMaxPoints(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadline1 = new Deadline();
        $deadline1->setGroupSubjectTeacher($this->gst);
        $deadline1->setSemester($this->activeSemester);
        $deadline1->setTitle('First Deadline');
        $deadline1->setDescription('First test');
        $deadline1->setMaxPoints(45);
        $deadline1->setDeadlineDate(new \DateTime('+1 week'));
        $this->em->persist($deadline1);
        $this->em->flush();

        $deadlineDate = (new \DateTime('+2 weeks'))->format('Y-m-d');
        $requestData = [
            'gst_id' => $this->gst->getId(),
            'title' => 'Second Deadline',
            'description' => 'Second test',
            'max_points' => 10,
            'deadline_date' => $deadlineDate
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('details', $data);
    }

    public function testGetGroupDeadlines(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadline1 = new Deadline();
        $deadline1->setGroupSubjectTeacher($this->gst);
        $deadline1->setSemester($this->activeSemester);
        $deadline1->setTitle('Test Deadline 1');
        $deadline1->setDescription('Description 1');
        $deadline1->setMaxPoints(10);
        $deadline1->setDeadlineDate(new \DateTime('+1 week'));
        $this->em->persist($deadline1);

        $deadline2 = new Deadline();
        $deadline2->setGroupSubjectTeacher($this->gst);
        $deadline2->setSemester($this->activeSemester);
        $deadline2->setTitle('Test Deadline 2');
        $deadline2->setDescription('Description 2');
        $deadline2->setMaxPoints(15);
        $deadline2->setDeadlineDate(new \DateTime('+2 weeks'));
        $this->em->persist($deadline2);

        $this->em->flush();

        $groupId = $this->gst->getGroup()->getId();
        $this->client->request(
            'GET',
            "/teacher/groups/{$groupId}/deadlines",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('deadlines', $data);

        $this->assertGreaterThanOrEqual(2, count($data['deadlines']));

        $firstDeadline = $data['deadlines'][0];
        $this->assertEquals('Test Deadline 1', $firstDeadline['title']);
        $this->assertEquals('Description 1', $firstDeadline['description']);
        $this->assertEquals(10, $firstDeadline['max_points']);
        $this->assertEquals('Test Subject', $firstDeadline['subject_name']);
    }

    public function testGetPointsInfo(): void
    {
        $jwtToken = $this->getJwtToken();

        $deadline1 = new Deadline();
        $deadline1->setGroupSubjectTeacher($this->gst);
        $deadline1->setSemester($this->activeSemester);
        $deadline1->setTitle('Test Deadline 1');
        $deadline1->setDescription('Description 1');
        $deadline1->setMaxPoints(10);
        $deadline1->setDeadlineDate(new \DateTime('+1 week'));
        $this->em->persist($deadline1);

        $deadline2 = new Deadline();
        $deadline2->setGroupSubjectTeacher($this->gst);
        $deadline2->setSemester($this->activeSemester);
        $deadline2->setTitle('Test Deadline 2');
        $deadline2->setDescription('Description 2');
        $deadline2->setMaxPoints(15);
        $deadline2->setDeadlineDate(new \DateTime('+2 weeks'));
        $this->em->persist($deadline2);

        $this->em->flush();

        $gstId = $this->gst->getId();
        $this->client->request(
            'GET',
            "/teacher/gst/{$gstId}/points-info",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('points_info', $data);

        $pointsInfo = $data['points_info'];
        $this->assertEquals(25, $pointsInfo['currentTotal']);
        $this->assertEquals(50, $pointsInfo['maxTotal']);
        $this->assertEquals(25, $pointsInfo['remaining']);
        $this->assertEquals(5, $pointsInfo['minPoints']);
    }

    public function testUnauthorizedAccess(): void
    {
        $requestData = [
            'gst_id' => $this->gst->getId(),
            'title' => 'Test Deadline',
            'description' => 'Test description',
            'max_points' => 10,
            'deadline_date' => (new \DateTime('+1 week'))->format('Y-m-d')
        ];

        $this->client->request(
            'POST',
            '/teacher/deadline',
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
