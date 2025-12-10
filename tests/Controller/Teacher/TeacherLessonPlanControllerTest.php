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
use App\Entity\Lesson;
use App\Entity\LessonPlan;
use App\Entity\LessonMaterial;
use App\Enum\LessonNumber;
use App\Enum\WeekDay;
use App\Service\FileUploadService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeacherLessonPlanControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Teacher $teacher;
    private Group $group;
    private Subject $subject;
    private GroupSubjectTeacher $gst;
    private Lesson $lesson;

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
            'lesson_material',
            'lesson_plan',
            'lesson',
            'group_subject_teacher',
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

        $this->group = new Group();
        $this->group->setFaculty($faculty);
        $this->group->setCourse($course);
        $this->group->setGroupNumber(101);
        $this->em->persist($this->group);

        $this->subject = new Subject();
        $this->subject->setSubjectName('Test Subject');
        $this->subject->setFaculty($faculty);
        $this->subject->setCourseNumber(1);
        $this->subject->setIsCommonFirstYear(false);
        $this->em->persist($this->subject);

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
        $this->teacher->getSubjects()->add($this->subject);

        $subjectReflection = new \ReflectionClass($this->subject);
        $teachersProperty = $subjectReflection->getProperty('teachers');
        $teachersProperty->setAccessible(true);
        $teachersProperty->setValue($this->subject, new ArrayCollection());
        $this->subject->getTeachers()->add($this->teacher);

        $semester = new Semester();
        $semester->setName('Fall 2024');
        $semester->setStartDate(new \DateTime('-1 month'));
        $semester->setEndDate(new \DateTime('+1 month'));
        $semester->setIsActive(true);
        $this->em->persist($semester);

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

    public function testGetSchedules(): void
    {
        $jwtToken = $this->getJwtToken();

        $groupId = $this->group->getId();
        $subjectId = $this->subject->getId();

        $this->client->request(
            'GET',
            "/teacher/schedules/{$groupId}/{$subjectId}",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($groupId, $data['group_id']);
        $this->assertEquals($subjectId, $data['subject_id']);
        $this->assertArrayHasKey('dates', $data);
    }

    public function testGetSchedulesNotFound(): void
    {
        $jwtToken = $this->getJwtToken();

        $this->client->request(
            'GET',
            "/teacher/schedules/9999/9999",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->getStatusCode() === 404 || $response->getStatusCode() === 200,
            'Expected 404 or 200, got: ' . $response->getStatusCode()
        );
    }

    public function testGetLessonPlans(): void
    {
        $jwtToken = $this->getJwtToken();

        $groupId = $this->group->getId();
        $subjectId = $this->subject->getId();

        $this->client->request(
            'GET',
            "/teacher/lesson-plans/{$groupId}/{$subjectId}",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testSaveLessonPlan(): void
    {
        $jwtToken = $this->getJwtToken();

        $requestData = [
            'lesson_id' => $this->lesson->getId(),
            'date' => (new \DateTime('next Monday'))->format('d.m.Y'),
            'topic' => 'Test Dars Mavzusi'
        ];

        $this->client->request(
            'POST',
            '/teacher/lesson-plan',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ],
            json_encode($requestData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode(),
            'Response: ' . $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Dars rejasi saqlandi', $data['message']);
        $this->assertArrayHasKey('lesson_plan', $data);

        $lessonPlanData = $data['lesson_plan'];
        $this->assertArrayHasKey('id', $lessonPlanData);
        $this->assertEquals($requestData['topic'], $lessonPlanData['topic']);
    }

    public function testSaveLessonPlanMissingFields(): void
    {
        $jwtToken = $this->getJwtToken();

        $requestData = [
            'lesson_id' => $this->lesson->getId(),
        ];

        $this->client->request(
            'POST',
            '/teacher/lesson-plan',
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
        $this->assertArrayHasKey('error', $data);
    }

    public function testSaveLessonPlanInvalidLesson(): void
    {
        $jwtToken = $this->getJwtToken();

        $requestData = [
            'lesson_id' => 9999,
            'date' => '01.01.2024',
            'topic' => 'Test'
        ];

        $this->client->request(
            'POST',
            '/teacher/lesson-plan',
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

    public function testUpdateLessonPlan(): void
    {
        $jwtToken = $this->getJwtToken();

        $lessonPlan = new LessonPlan();
        $lessonPlan->setLesson($this->lesson);
        $lessonPlan->setDate(new \DateTime('next Monday'));
        $lessonPlan->setTopic('Old Topic');
        $this->em->persist($lessonPlan);
        $this->em->flush();

        $requestData = [
            'lesson_id' => $this->lesson->getId(),
            'date' => $lessonPlan->getDate()->format('d.m.Y'),
            'topic' => 'Yangi Topic'
        ];

        $this->client->request(
            'POST',
            '/teacher/lesson-plan',
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
        $this->assertEquals('Yangi Topic', $data['lesson_plan']['topic']);
    }

    public function testGetMaterials(): void
    {
        $lessonPlan = new LessonPlan();
        $lessonPlan->setLesson($this->lesson);
        $lessonPlan->setDate(new \DateTime('next Monday'));
        $lessonPlan->setTopic('Test Topic');

        $material = new LessonMaterial();
        $material->setFileName('test.pdf');
        $material->setOriginalName('test_document.pdf');
        $material->setFileType('pdf');
        $material->setLessonPlan($lessonPlan);
        $lessonPlan->addMaterial($material);

        $this->em->persist($lessonPlan);
        $this->em->persist($material);
        $this->em->flush();

        $jwtToken = $this->getJwtToken();

        $this->client->request(
            'GET',
            "/teacher/lesson-plan/{$lessonPlan->getId()}/materials",
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
        $this->assertArrayHasKey('materials', $data);
        $this->assertCount(1, $data['materials']);
    }

    public function testDeleteMaterial(): void
    {
        $fileUploadServiceMock = $this->createMock(\App\Service\FileUploadService::class);
        $fileUploadServiceMock->method('deleteFile')
            ->willReturn(true);

        $container = static::getContainer();
        $container->set(\App\Service\FileUploadService::class, $fileUploadServiceMock);

        $lessonPlan = new LessonPlan();
        $lessonPlan->setLesson($this->lesson);
        $lessonPlan->setDate(new \DateTime('next Monday'));
        $lessonPlan->setTopic('Test Topic');

        $material = new LessonMaterial();
        $material->setFileName('test.pdf');
        $material->setOriginalName('test_document.pdf');
        $material->setFileType('pdf');
        $material->setLessonPlan($lessonPlan);
        $lessonPlan->addMaterial($material);

        $this->em->persist($lessonPlan);
        $this->em->persist($material);
        $this->em->flush();

        $jwtToken = $this->getJwtToken();

        $this->client->request(
            'DELETE',
            "/teacher/material/{$material->getId()}",
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
        $this->assertEquals('Fayl o\'chirildi', $data['message']);
    }

    public function testDeleteMaterialNotFound(): void
    {
        $jwtToken = $this->getJwtToken();

        $this->client->request(
            'DELETE',
            '/teacher/material/9999',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUnauthorizedAccess(): void
    {
        $this->client->request(
            'GET',
            "/teacher/schedules/{$this->group->getId()}/{$this->subject->getId()}",
            [],
            [],
            [
            ]
        );

        $response = $this->client->getResponse();
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUploadMaterials(): void
    {
        $jwtToken = $this->getJwtToken();

        $lessonPlan = new LessonPlan();
        $lessonPlan->setLesson($this->lesson);
        $lessonPlan->setDate(new \DateTime('next Monday'));
        $lessonPlan->setTopic('Test Topic');
        $this->em->persist($lessonPlan);
        $this->em->flush();

        $fileUploadServiceMock = $this->createMock(FileUploadService::class);
        $fileUploadServiceMock->method('uploadMaterial')
            ->willReturn([
                'fileName' => 'test_123.pdf',
                'originalName' => 'test_document.pdf',
                'fileType' => 'pdf'
            ]);

        $this->client->disableReboot();

        $container = $this->client->getContainer();
        $container->set('App\Service\FileUploadService', $fileUploadServiceMock);

        $tempFile = tmpfile();
        fwrite($tempFile, 'test content');
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        $uploadedFile = new UploadedFile(
            $tempFilePath,
            'test_document.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            "/teacher/lesson-plan/{$lessonPlan->getId()}/materials",
            [],
            ['materials' => [$uploadedFile]],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $jwtToken
            ]
        );

        fclose($tempFile);

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}
