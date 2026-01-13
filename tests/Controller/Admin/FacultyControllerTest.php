<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Person;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FacultyControllerTest extends WebTestCase
{
    public function testAdminCanCreateFaculty(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Faculty f')->execute();
        $em->flush();

        $hasher = $container->get('security.user_password_hasher');
        $admin = new Person();
        $admin->setLogin('admin_' . rand(1, 1000));
        $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);

        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"login":"' . $admin->getLogin() . '","password":"admin123"}'
        );

        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/admin/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"name":"Matematika fakulteti"}'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('name', $data);
        $this->assertEquals('Matematika fakulteti', $data['name']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCannotCreateFacultyWithoutName(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Faculty f')->execute();
        $em->flush();

        $hasher = $container->get('security.user_password_hasher');
        $admin = new Person();
        $admin->setLogin('admin_' . rand(1, 1000));
        $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);

        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"login":"' . $admin->getLogin() . '","password":"admin123"}'
        );

        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/admin/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    public function testCannotCreateDuplicateFaculty(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Faculty f')->execute();
        $em->flush();

        $hasher = $container->get('security.user_password_hasher');
        $admin = new Person();
        $admin->setLogin('admin_' . rand(1, 1000));
        $admin->setPassword($hasher->hashPassword($admin, 'admin123'));
        $admin->setRoles(['ROLE_ADMIN']);

        $em->persist($admin);
        $em->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"login":"' . $admin->getLogin() . '","password":"admin123"}'
        );

        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/admin/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"name":"Fizika fakulteti"}'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/admin/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"name":"Fizika fakulteti"}'
        );

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    public function testStudentCannotCreateFaculty(): void
    {
        $client = static::createClient();

        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Faculty f')->execute();
        $em->flush();

        $hasher = $container->get('security.user_password_hasher');
        $student = new Person();
        $student->setLogin('student_' . rand(1, 1000));
        $student->setPassword($hasher->hashPassword($student, 'student123'));
        $student->setRoles(['ROLE_STUDENT']);

        $em->persist($student);
        $em->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"login":"' . $student->getLogin() . '","password":"student123"}'
        );

        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);
        $client->request(
            'POST',
            '/admin/create-faculty',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"name":"Kimyo fakulteti"}'
        );

        $status = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(200, $status);
    }
}
