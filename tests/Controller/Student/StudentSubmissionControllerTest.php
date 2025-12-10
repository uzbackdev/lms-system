<?php

namespace App\Tests\Controller\Student;

use App\Entity\Course;
use App\Entity\Deadline;
use App\Entity\DeadlineSubmission;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Person;
use App\Entity\Semester;
use App\Entity\Student;
use App\Entity\Subject;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StudentSubmissionControllerTest extends WebTestCase
{
    private ?KernelBrowser $client = null;
    private ?EntityManagerInterface $em = null;
    private ?UserPasswordHasherInterface $passwordHasher = null;

    private ?Person $person = null;
    private ?Student $student = null;
    private ?Deadline $deadline = null;
    private ?Faculty $faculty = null;
    private ?Course $course = null;
    private ?Group $group = null;
    private ?Semester $semester = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->em->getConnection()->beginTransaction();

        $this->createTestData();
    }

    private function createTestData(): void
    {
        $existingCourseNumbers = $this->em->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->select('c.number')
            ->getQuery()
            ->getSingleColumnResult();

        $availableNumbers = array_diff([1, 2, 3, 4], $existingCourseNumbers);
        $courseNumber = !empty($availableNumbers) ? reset($availableNumbers) : 1;

        $uniqueName = 'TF' . substr(uniqid(), 0, 8);
        $this->faculty = new Faculty();
        $this->faculty->setName($uniqueName);
        $this->em->persist($this->faculty);

        $this->course = new Course();
        $this->course->setNumber($courseNumber);
        $this->em->persist($this->course);

        $shortId = substr(uniqid(), 0, 6);
        $login = 's' . $shortId;

        $this->person = new Person();
        $this->person->setLogin($login);
        $this->person->setRoles(['ROLE_STUDENT']);

        $hashedPassword = $this->passwordHasher->hashPassword($this->person, 'password123');
        $this->person->setPassword($hashedPassword);
        $this->em->persist($this->person);

        $this->group = new Group();
        $this->group->setGroupNumber(101);
        $this->group->setFaculty($this->faculty);
        $this->group->setCourse($this->course);
        $this->em->persist($this->group);

        $this->student = new Student();
        $this->student->setPerson($this->person);
        $this->student->setGroup($this->group);
        $this->student->setName('John');
        $this->student->setSurname('Doe');
        $this->student->setAddress('Test Address 123');

        $this->person->setStudent($this->student);
        $this->em->persist($this->student);

        $teacherShortId = substr(uniqid(), 0, 6);
        $teacherLogin = 't' . $teacherShortId;

        $teacherPerson = new Person();
        $teacherPerson->setLogin($teacherLogin);
        $teacherPerson->setRoles(['ROLE_TEACHER']);
        $teacherPassword = $this->passwordHasher->hashPassword($teacherPerson, 'teacher123');
        $teacherPerson->setPassword($teacherPassword);
        $this->em->persist($teacherPerson);

        $teacher = new Teacher();
        $teacher->setName('Jane');
        $teacher->setSurname('Smith');
        $teacher->setFaculty($this->faculty);
        $teacher->setPerson($teacherPerson);
        $teacherPerson->setTeacher($teacher);
        $this->em->persist($teacher);

        $subject = new Subject();
        $subject->setSubjectName('Subj' . substr(uniqid(), 0, 6));
        $subject->setFaculty($this->faculty);
        $subject->setCourseNumber($courseNumber);
        $subject->setIsCommonFirstYear(false);
        $this->em->persist($subject);

        $groupSubjectTeacher = new GroupSubjectTeacher();
        $groupSubjectTeacher->setGroup($this->group);
        $groupSubjectTeacher->setSubject($subject);
        $groupSubjectTeacher->setTeacher($teacher);
        $this->em->persist($groupSubjectTeacher);

        $this->semester = new Semester();
        $this->semester->setName('S' . substr(uniqid(), 0, 8));
        $this->semester->setStartDate(new \DateTime('2024-01-15'));
        $this->semester->setEndDate(new \DateTime('2024-05-30'));
        $this->semester->setIsActive(true);
        $this->em->persist($this->semester);

        $this->deadline = new Deadline();
        $this->deadline->setGroupSubjectTeacher($groupSubjectTeacher);
        $this->deadline->setSemester($this->semester);
        $this->deadline->setTitle('DA' . substr(uniqid(), 0, 8));
        $this->deadline->setDescription('Test assignment description');
        $this->deadline->setMaxPoints(100);

        $deadlineDate = new \DateTime('+1 day');
        $this->deadline->setDeadlineDate($deadlineDate);

        $this->em->persist($this->deadline);
        $this->em->flush();
    }

    public function testSubmitDeadlineSuccess(): void
    {
        $this->client->loginUser($this->person);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'Test PDF content for submission');
        $uploadedFile = new UploadedFile(
            $tempFile,
            'test_assignment.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit',
            [],
            ['file' => $uploadedFile]
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertEquals('Topshiriq muvaffaqiyatli topshirildi', $response['message']);
        $this->assertArrayHasKey('submission', $response);

        $submission = $this->em->getRepository(DeadlineSubmission::class)->findOneBy([
            'deadline' => $this->deadline,
            'student' => $this->student
        ]);

        $this->assertNotNull($submission);
        $this->assertEquals('submitted', $submission->getStatus());

        unlink($tempFile);
    }

    public function testSubmitDeadlineUnauthenticated(): void
    {
        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit'
        );

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Authentication required', $response['error']);
    }

    public function testSubmitDeadlineNotFound(): void
    {
        $this->client->loginUser($this->person);

        $this->client->request(
            'POST',
            '/student/deadlines/999999/submit'
        );

        $this->assertResponseStatusCodeSame(404);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Deadline topilmadi', $response['error']);
    }

    public function testSubmitDeadlineWrongGroup(): void
    {
        $otherGroup = new Group();
        $otherGroup->setGroupNumber(202);
        $otherGroup->setFaculty($this->faculty);
        $otherGroup->setCourse($this->course);
        $this->em->persist($otherGroup);
        $this->em->flush();

        $subject = $this->em->getRepository(Subject::class)->findOneBy([]);
        $teacher = $this->em->getRepository(Teacher::class)->findOneBy([]);

        if ($subject && $teacher) {
            $otherGroupSubjectTeacher = new GroupSubjectTeacher();
            $otherGroupSubjectTeacher->setGroup($otherGroup);
            $otherGroupSubjectTeacher->setSubject($subject);
            $otherGroupSubjectTeacher->setTeacher($teacher);
            $this->em->persist($otherGroupSubjectTeacher);
            $this->em->flush();

            $otherDeadline = new Deadline();
            $otherDeadline->setGroupSubjectTeacher($otherGroupSubjectTeacher);
            $otherDeadline->setSemester($this->semester);
            $otherDeadline->setTitle('Other DA');
            $otherDeadline->setDescription('For other group only');
            $otherDeadline->setMaxPoints(100);
            $otherDeadline->setDeadlineDate(new \DateTime('+1 day'));
            $this->em->persist($otherDeadline);
            $this->em->flush();

            $this->client->loginUser($this->person);

            $tempFile = tempnam(sys_get_temp_dir(), 'test_');
            file_put_contents($tempFile, 'Test content');
            $uploadedFile = new UploadedFile(
                $tempFile,
                'test.pdf',
                'application/pdf',
                null,
                true
            );

            $this->client->request(
                'POST',
                '/student/deadlines/' . $otherDeadline->getId() . '/submit',
                [],
                ['file' => $uploadedFile]
            );

            $this->assertResponseStatusCodeSame(403);
            $response = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('Bu deadline sizning guruhingizga tegishli emas', $response['error']);

            unlink($tempFile);
        } else {
            $this->markTestSkipped('Subject or Teacher not found');
        }
    }

    public function testSubmitDeadlinePastDeadline(): void
    {
        $groupSubjectTeachers = $this->em->getRepository(GroupSubjectTeacher::class)->findAll();
        if (!empty($groupSubjectTeachers)) {
            $pastDeadline = new Deadline();
            $pastDeadline->setGroupSubjectTeacher($groupSubjectTeachers[0]);
            $pastDeadline->setSemester($this->semester);
            $pastDeadline->setTitle('Past DA');
            $pastDeadline->setDescription('This deadline has passed');
            $pastDeadline->setMaxPoints(100);

            $pastDate = new \DateTime('-1 day');
            $pastDeadline->setDeadlineDate($pastDate);

            $this->em->persist($pastDeadline);
            $this->em->flush();

            $this->client->loginUser($this->person);

            $tempFile = tempnam(sys_get_temp_dir(), 'test_');
            file_put_contents($tempFile, 'Test file content');
            $uploadedFile = new UploadedFile(
                $tempFile,
                'test_document.pdf',
                'application/pdf',
                null,
                true
            );

            $this->client->request(
                'POST',
                '/student/deadlines/' . $pastDeadline->getId() . '/submit',
                [],
                ['file' => $uploadedFile]
            );

            $this->assertResponseStatusCodeSame(400);
            $response = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('Deadline muddati o\'tgan. Topshirish mumkin emas', $response['error']);

            unlink($tempFile);
        } else {
            $this->markTestSkipped('GroupSubjectTeacher not found');
        }
    }

    public function testSubmitDeadlineAlreadySubmitted(): void
    {
        $this->client->loginUser($this->person);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'First submission content');
        $uploadedFile = new UploadedFile(
            $tempFile,
            'first_submission.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit',
            [],
            ['file' => $uploadedFile]
        );

        $this->assertResponseIsSuccessful();

        $tempFile2 = tempnam(sys_get_temp_dir(), 'test2_');
        file_put_contents($tempFile2, 'Second submission content');
        $uploadedFile2 = new UploadedFile(
            $tempFile2,
            'second_submission.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit',
            [],
            ['file' => $uploadedFile2]
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Siz allaqachon bu topshiriqni topshirgansiz', $response['error']);

        unlink($tempFile);
        unlink($tempFile2);
    }

    public function testSubmitDeadlineNoFile(): void
    {
        $this->client->loginUser($this->person);

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit'
        );

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Fayl yuklanishi shart', $response['error']);
    }

    public function testSubmitDeadlineInvalidFileType(): void
    {
        $this->client->loginUser($this->person);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'Test file content');
        $uploadedFile = new UploadedFile(
            $tempFile,
            'test_document.txt',
            'text/plain',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit',
            [],
            ['file' => $uploadedFile]
        );

        $this->assertResponseStatusCodeSame(500);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Ruxsat etilmagan fayl formati', $response['error']);

        unlink($tempFile);
    }

    public function testSubmitDeadlineValidFileTypes(): void
    {
        $this->client->loginUser($this->person);

        $validExtensions = ['pdf', 'docx', 'doc', 'ppt', 'pptx', 'xlsx', 'xls', 'jpg', 'jpeg', 'png', 'zip', 'rar'];

        foreach ($validExtensions as $extension) {
            $groupSubjectTeachers = $this->em->getRepository(GroupSubjectTeacher::class)->findAll();
            if (!empty($groupSubjectTeachers)) {
                $newDeadline = new Deadline();
                $newDeadline->setGroupSubjectTeacher($groupSubjectTeachers[0]);
                $newDeadline->setSemester($this->semester);
                $newDeadline->setTitle("DA-$extension");
                $newDeadline->setDescription("Test assignment with $extension file");
                $newDeadline->setMaxPoints(100);
                $newDeadline->setDeadlineDate(new \DateTime('+1 day'));
                $this->em->persist($newDeadline);
                $this->em->flush();

                $tempFile = tempnam(sys_get_temp_dir(), 'test_');
                file_put_contents($tempFile, "Test content for $extension");

                $uploadedFile = new UploadedFile(
                    $tempFile,
                    "test_file.$extension",
                    $this->getMimeType($extension),
                    null,
                    true
                );

                $this->client->request(
                    'POST',
                    '/student/deadlines/' . $newDeadline->getId() . '/submit',
                    [],
                    ['file' => $uploadedFile]
                );

                $response = json_decode($this->client->getResponse()->getContent(), true);

                $this->assertResponseIsSuccessful();
                $this->assertTrue($response['success']);

                unlink($tempFile);

                $this->em->remove($newDeadline);
                $this->em->flush();
            } else {
                $this->markTestSkipped('GroupSubjectTeacher not found');
                break;
            }
        }
    }

    public function testSubmitDeadlineAsTeacherShouldFail(): void
    {
        $teacherShortId = substr(uniqid(), 0, 6);
        $teacherLogin = 'tt' . $teacherShortId;

        $teacherPerson = new Person();
        $teacherPerson->setLogin($teacherLogin);
        $teacherPerson->setRoles(['ROLE_TEACHER']);
        $hashedPassword = $this->passwordHasher->hashPassword($teacherPerson, 'teacher123');
        $teacherPerson->setPassword($hashedPassword);
        $this->em->persist($teacherPerson);

        $teacher = new Teacher();
        $teacher->setName('Test');
        $teacher->setSurname('Teacher');
        $teacher->setFaculty($this->faculty);
        $teacher->setPerson($teacherPerson);
        $teacherPerson->setTeacher($teacher);
        $this->em->persist($teacher);
        $this->em->flush();

        $this->client->loginUser($teacherPerson);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'Test content');
        $uploadedFile = new UploadedFile(
            $tempFile,
            'test.pdf',
            'application/pdf',
            null,
            true
        );

        $this->client->request(
            'POST',
            '/student/deadlines/' . $this->deadline->getId() . '/submit',
            [],
            ['file' => $uploadedFile]
        );

        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertTrue(in_array($statusCode, [403, 404]),
            "Expected 403 or 404, got {$statusCode}");

        unlink($tempFile);
    }

    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed'
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->em && $this->em->getConnection()->isTransactionActive()) {
            $this->em->getConnection()->rollback();
        }

        if ($this->em) {
            $this->em->close();
            $this->em = null;
        }
    }
}
