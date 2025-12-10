<?php

namespace App\Tests\Controller\Public;

use App\Entity\Person;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testLoginSuccess(): void
    {
        $client = static::createClient();

        $login = 'student123';
        $password = 'password123';

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $passwordHasher = $client->getContainer()->get('security.user_password_hasher');

        $user = new Person();
        $user->setLogin($login);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_STUDENT']);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $login,
                'password' => $password
            ])
        );

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $this->assertJson($response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWrongPassword(): void
    {
        $client = static::createClient();

        $login = 'student456';
        $password = 'correctpass';

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $passwordHasher = $client->getContainer()->get('security.user_password_asher');

        $user = new Person();
        $user->setLogin($login);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_STUDENT']);

        $entityManager->persist($user);
        $entityManager->flush();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => $login,
                'password' => 'wrongpassword'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginUserNotFound(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'login' => 'nonexistent',
                'password' => 'password'
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
