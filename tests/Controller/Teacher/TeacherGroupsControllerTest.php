<?php

namespace App\Tests\Controller\Teacher;

use App\Entity\Person;
use App\Entity\Teacher;
use App\Entity\Faculty;
use App\Entity\Group;
use App\Entity\Course;
use App\Entity\Subject;
use App\Entity\GroupSubjectTeacher;
use App\Entity\Student;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TeacherGroupsControllerTest extends WebTestCase
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
    private $student;

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

        $studentPerson = new Person();
        $studentPerson->setLogin($this->generateLogin('stu_'));
        $studentPerson->setPassword($this->hasher->hashPassword($studentPerson, 'password123'));
        $studentPerson->setRoles(['ROLE_STUDENT']);
        $this->em->persist($studentPerson);

        $this->student = new Student();
        $this->student->setName('Alice');
        $this->student->setSurname('Johnson');
        $this->student->setAddress('123 Main St');

        $this->student->setPerson($studentPerson);
        $studentPerson->setStudent($this->student);

        $this->student->setGroup($this->group);
        $this->em->persist($this->student);

        $this->em->flush();

        $teacherUserId = $this->teacherUser->getId();
        $facultyId = $this->faculty->getId();
        $courseId = $this->course->getId();
        $groupId = $this->group->getId();
        $subjectId = $this->subject->getId();

        $this->em->clear();

        $this->teacherUser = $this->em->getRepository(Person::class)->find($teacherUserId);
        $this->teacher = $this->teacherUser->getTeacher();
        $this->faculty = $this->em->getRepository(Faculty::class)->find($facultyId);
        $this->course = $this->em->getRepository(Course::class)->find($courseId);
        $this->group = $this->em->getRepository(Group::class)->find($groupId);
        $this->subject = $this->em->getRepository(Subject::class)->find($subjectId);
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

    public function testTeacherCanGetMyGroups(): void
    {
        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/my-groups',
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

        $this->assertArrayHasKey('groups', $responseData);
        $groups = $responseData['groups'];

        $this->assertIsArray($groups);
        $this->assertCount(1, $groups);

        $firstGroup = $groups[0];

        $this->assertArrayHasKey('group_id', $firstGroup);
        $this->assertArrayHasKey('group_number', $firstGroup);
        $this->assertArrayHasKey('course_number', $firstGroup);
        $this->assertArrayHasKey('subjects_count', $firstGroup);

        $this->assertEquals($this->group->getId(), $firstGroup['group_id']);
        $this->assertEquals(101, $firstGroup['group_number']);
        $this->assertEquals(1, $firstGroup['course_number']);
        $this->assertEquals(1, $firstGroup['subjects_count']);
    }

    public function testTeacherWithMultipleGroups(): void
    {
        $course = $this->em->getRepository(Course::class)->findOneBy(['number' => 1]);
        if (!$course) {
            $course = new Course();
            $course->setNumber(1);
            $this->em->persist($course);
            $this->em->flush();
        }

        $group2 = new Group();
        $group2->setGroupNumber(102);
        $group2->setCourse($course);
        $group2->setFaculty($this->faculty);
        $this->em->persist($group2);

        $subject2 = new Subject();
        $subject2->setSubjectName('Physics');
        $subject2->setCourseNumber(1);
        $subject2->setFaculty($this->faculty);
        $this->em->persist($subject2);

        $subject3 = new Subject();
        $subject3->setSubjectName('Chemistry');
        $subject3->setCourseNumber(1);
        $subject3->setFaculty($this->faculty);
        $this->em->persist($subject3);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($group2);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $gst3 = new GroupSubjectTeacher();
        $gst3->setGroup($this->group);
        $gst3->setSubject($subject3);
        $gst3->setTeacher($this->teacher);
        $this->em->persist($gst3);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/my-groups',
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
        $groups = $responseData['groups'];

        $this->assertCount(2, $groups);

        $groupNumbers = array_map(function($group) {
            return $group['group_number'];
        }, $groups);

        $this->assertContains(101, $groupNumbers);
        $this->assertContains(102, $groupNumbers);

        foreach ($groups as $group) {
            if ($group['group_number'] === 101) {
                $this->assertEquals(2, $group['subjects_count']);
            } elseif ($group['group_number'] === 102) {
                $this->assertEquals(1, $group['subjects_count']);
            }
        }
    }

    public function testTeacherWithoutGroups(): void
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
        if (!$faculty) {
            $faculty = new Faculty();
            $faculty->setName('Computer Science');
            $this->em->persist($faculty);
            $this->em->flush();
        }
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
            '/teacher/my-groups',
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
        $groups = $responseData['groups'];

        $this->assertIsArray($groups);
        $this->assertEmpty($groups);
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
            '/teacher/my-groups',
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

    public function testTeacherCanGetGroupSubjects(): void
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

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            "/teacher/my-groups/{$this->group->getId()}/subjects",
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

        $this->assertArrayHasKey('subjects', $responseData);
        $subjects = $responseData['subjects'];

        $this->assertIsArray($subjects);

        $subjectNames = array_map(function($subject) {
            return $subject['subject_name'] ?? null;
        }, $subjects);

        $this->assertContains('Mathematics', $subjectNames);
        $this->assertContains('Physics', $subjectNames);

        foreach ($subjects as $subject) {
            $this->assertArrayHasKey('id', $subject);
            $this->assertArrayHasKey('subject_name', $subject);
            $this->assertArrayHasKey('subject_id', $subject);
        }
    }

    public function testTeacherCannotGetOtherTeachersGroupSubjects(): void
    {
        $physicsSubject = new Subject();
        $physicsSubject->setSubjectName('Physics');
        $physicsSubject->setCourseNumber(1);
        $physicsSubject->setFaculty($this->faculty);
        $this->em->persist($physicsSubject);

        $physicsGst = new GroupSubjectTeacher();
        $physicsGst->setGroup($this->group);
        $physicsGst->setSubject($physicsSubject);
        $physicsGst->setTeacher($this->teacher);
        $this->em->persist($physicsGst);

        $teacher2Person = new Person();
        $teacher2Person->setLogin($this->generateLogin('tch3_'));
        $teacher2Person->setPassword($this->hasher->hashPassword($teacher2Person, 'password123'));
        $teacher2Person->setRoles(['ROLE_TEACHER']);
        $this->em->persist($teacher2Person);

        $teacher2 = new Teacher();
        $teacher2->setName('Jane');
        $teacher2->setSurname('Smith');
        $teacher2->setFaculty($this->faculty);

        $teacher2->setPerson($teacher2Person);
        $teacher2Person->setTeacher($teacher2);

        $this->em->persist($teacher2);

        $otherSubject = new Subject();
        $otherSubject->setSubjectName('Biology');
        $otherSubject->setCourseNumber(1);
        $otherSubject->setFaculty($this->faculty);
        $this->em->persist($otherSubject);

        $otherGst = new GroupSubjectTeacher();
        $otherGst->setGroup($this->group);
        $otherGst->setSubject($otherSubject);
        $otherGst->setTeacher($teacher2);
        $this->em->persist($otherGst);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            "/teacher/my-groups/{$this->group->getId()}/subjects",
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
        $subjects = $responseData['subjects'];

        $this->assertIsArray($subjects);

        $subjectNames = array_map(function($subject) {
            return $subject['subject_name'] ?? null;
        }, $subjects);

        $this->assertContains('Mathematics', $subjectNames);
        $this->assertContains('Physics', $subjectNames);
        $this->assertNotContains('Biology', $subjectNames);
    }

    public function testTeacherCanGetGroupStudents(): void
    {
        $studentPerson2 = new Person();
        $studentPerson2->setLogin($this->generateLogin('stu2_'));
        $studentPerson2->setPassword($this->hasher->hashPassword($studentPerson2, 'password123'));
        $studentPerson2->setRoles(['ROLE_STUDENT']);
        $this->em->persist($studentPerson2);

        $student2 = new Student();
        $student2->setName('Bob');
        $student2->setSurname('Brown');
        $student2->setAddress('456 Oak St');

        $student2->setPerson($studentPerson2);
        $studentPerson2->setStudent($student2);

        $student2->setGroup($this->group);
        $this->em->persist($student2);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            "/teacher/groups/{$this->group->getId()}/subjects/{$this->subject->getId()}/students",
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

        $this->assertArrayHasKey('group', $responseData);
        $groupData = $responseData['group'];

        $this->assertEquals($this->group->getId(), $groupData['id']);
        $this->assertEquals(101, $groupData['group_number']);
        $this->assertEquals(1, $groupData['course']);
        $this->assertEquals('Computer Science', $groupData['faculty']);

        $this->assertArrayHasKey('subject', $responseData);
        $subjectData = $responseData['subject'];

        $this->assertEquals($this->subject->getId(), $subjectData['id']);
        $this->assertEquals('Mathematics', $subjectData['name']);

        $this->assertArrayHasKey('students', $responseData);
        $students = $responseData['students'];

        $this->assertIsArray($students);
        $this->assertCount(2, $students);

        $studentNames = array_map(function($student) {
            return $student['name'] . ' ' . $student['surname'];
        }, $students);

        $this->assertContains('Alice Johnson', $studentNames);
        $this->assertContains('Bob Brown', $studentNames);
    }

    public function testTeacherCannotGetStudentsIfNotTeaching(): void
    {
        $otherSubject = new Subject();
        $otherSubject->setSubjectName('Biology');
        $otherSubject->setCourseNumber(1);
        $otherSubject->setFaculty($this->faculty);
        $this->em->persist($otherSubject);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            "/teacher/groups/{$this->group->getId()}/subjects/{$otherSubject->getId()}/students",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Siz ushbu guruhga bu fandan dars bermaysiz', $responseData['error']);
    }

    public function testTeacherCannotGetNonexistentGroupStudents(): void
    {
        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $nonExistentGroupId = 99999;

        $this->client->request(
            'GET',
            "/teacher/groups/{$nonExistentGroupId}/subjects/{$this->subject->getId()}/students",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals("Ma'lumot topilmadi", $responseData['error']);
    }

    public function testTeacherCannotGetNonexistentSubjectStudents(): void
    {
        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $nonExistentSubjectId = 99999;

        $this->client->request(
            'GET',
            "/teacher/groups/{$this->group->getId()}/subjects/{$nonExistentSubjectId}/students",
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ]
        );

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals("Ma'lumot topilmadi", $responseData['error']);
    }

    public function testUnauthenticatedAccessFails(): void
    {
        $this->client->request('GET', '/teacher/my-groups');

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', "/teacher/my-groups/{$this->group->getId()}/subjects");

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        $this->client->request('GET', "/teacher/groups/{$this->group->getId()}/subjects/{$this->subject->getId()}/students");

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testNonTeacherCannotAccess(): void
    {
        $student = $this->em->getRepository(Student::class)->findOneBy(['name' => 'Alice']);
        $studentPerson = $student->getPerson();

        $token = $this->loginAndGetToken($studentPerson, 'password123');

        if (!$token) {
            $this->fail('Student login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/my-groups',
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

    public function testStudentNotFoundInGroupStudents(): void
    {
        $emptyGroup = new Group();
        $emptyGroup->setGroupNumber(999);

        $course = $this->em->getRepository(Course::class)->findOneBy(['number' => 1]);
        if (!$course) {
            $course = new Course();
            $course->setNumber(1);
            $this->em->persist($course);
            $this->em->flush();
        }
        $emptyGroup->setCourse($course);

        $emptyGroup->setFaculty($this->faculty);
        $this->em->persist($emptyGroup);

        $gstForEmptyGroup = new GroupSubjectTeacher();
        $gstForEmptyGroup->setGroup($emptyGroup);
        $gstForEmptyGroup->setSubject($this->subject);
        $gstForEmptyGroup->setTeacher($this->teacher);
        $this->em->persist($gstForEmptyGroup);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            "/teacher/groups/{$emptyGroup->getId()}/subjects/{$this->subject->getId()}/students",
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
        $students = $responseData['students'];

        $this->assertIsArray($students);
        $this->assertEmpty($students);
    }

    public function testTeacherAccessWithDifferentCourses(): void
    {
        $course2 = $this->em->getRepository(Course::class)->findOneBy(['number' => 2]);
        if (!$course2) {
            $course2 = new Course();
            $course2->setNumber(2);
            $this->em->persist($course2);
            $this->em->flush();
        }

        $group2 = new Group();
        $group2->setGroupNumber(201);
        $group2->setCourse($course2);
        $group2->setFaculty($this->faculty);
        $this->em->persist($group2);

        $subject2 = new Subject();
        $subject2->setSubjectName('Advanced Mathematics');
        $subject2->setCourseNumber(2);
        $subject2->setFaculty($this->faculty);
        $this->em->persist($subject2);

        $gst2 = new GroupSubjectTeacher();
        $gst2->setGroup($group2);
        $gst2->setSubject($subject2);
        $gst2->setTeacher($this->teacher);
        $this->em->persist($gst2);

        $this->em->flush();

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/my-groups',
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
        $groups = $responseData['groups'];

        $this->assertCount(2, $groups);

        $courseNumbers = array_map(function($group) {
            return $group['course_number'];
        }, $groups);

        $this->assertContains(1, $courseNumbers);
        $this->assertContains(2, $courseNumbers);

        $groupNumbers = array_map(function($group) {
            return $group['group_number'];
        }, $groups);

        $this->assertContains(101, $groupNumbers);
        $this->assertContains(201, $groupNumbers);
    }

    public function testEmptyDatabaseReturnsEmptyGroups(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('DELETE FROM group_subject_teacher');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $token = $this->loginAndGetToken($this->teacherUser, 'password123');

        if (!$token) {
            $this->fail('Teacher login muvaffaqiyatsiz');
        }

        $this->client->request(
            'GET',
            '/teacher/my-groups',
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
        $groups = $responseData['groups'];

        $this->assertIsArray($groups);
        $this->assertEmpty($groups);
    }

    public function testPersonTeacherRelationship(): void
    {
        $this->assertNotNull($this->teacherUser->getTeacher());
        $this->assertNotNull($this->teacher->getPerson());

        $this->assertEquals($this->teacher->getId(), $this->teacherUser->getTeacher()->getId());
        $this->assertEquals($this->teacherUser->getId(), $this->teacher->getPerson()->getId());

        $this->assertTrue($this->teacherUser->isTeacher());
        $this->assertFalse($this->teacherUser->isStudent());
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
        $this->student = null;
    }
}
