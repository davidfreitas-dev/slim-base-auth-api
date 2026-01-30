<?php

declare(strict_types=1);

namespace Tests\Functional\Api\V1\Profile;

use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\RoleRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use Fig\Http\Message\StatusCodeInterface;
use Slim\Psr7\UploadedFile;
use Tests\Functional\FunctionalTestCase;
use Faker\Factory;

class UploadProfileImageTest extends FunctionalTestCase
{
    private UserRepositoryInterface $userRepository;
    private PersonRepositoryInterface $personRepository;
    private RoleRepositoryInterface $roleRepository;
    private User $user;
    private string $accessToken;
    private \Faker\Generator $faker;
    private array $uploadedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->app->getContainer()->get(UserRepositoryInterface::class);
        $this->personRepository = $this->app->getContainer()->get(PersonRepositoryInterface::class);
        $this->roleRepository = $this->app->getContainer()->get(RoleRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');
        $this->setUpUser();
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedFiles as $filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        parent::tearDown();
    }

    private function setUpUser(): void
    {
        $person = new Person(
            name: 'testuser_upload',
            email: 'upload@example.com',
            cpfcnpj: CpfCnpj::fromString($this->faker->cpf())
        );
        $person = $this->personRepository->create($person);

        $role = $this->roleRepository->findByName('user');
        if (!$role instanceof Role) {
            throw new NotFoundException("Role 'user' not found in the database.");
        }

        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $this->user = new User(
            person: $person,
            password: $hashedPassword,
            role: $role,
            isActive: true,
            isVerified: true
        );
        $this->userRepository->create($this->user);

        $response = $this->sendRequest('POST', '/api/v1/auth/login', [
            'email' => 'upload@example.com',
            'password' => $password,
        ]);

        $loginData = json_decode((string) $response->getBody(), true);
        $this->accessToken = $loginData['data']['access_token'];
    }

    private function createDummyImage(string $filename): string
    {
        $filepath = sys_get_temp_dir() . '/' . $filename;
        $image = imagecreatetruecolor(10, 10);
        imagepng($image, $filepath);
        imagedestroy($image);
        return $filepath;
    }

    public function testUpdateProfileWithImageUploadReturnsOk(): void
    {
        // Arrange
        $imagePath = $this->createDummyImage('test_avatar.png');
        $this->uploadedFiles[] = $imagePath; // For cleanup

        $uploadedFile = new UploadedFile(
            $imagePath,
            'test_avatar.png',
            'image/png',
            filesize($imagePath)
        );

        $files = ['profile_image' => $uploadedFile];
        $body = ['name' => 'User With Avatar'];

        // Act
        // The user controller uses a PUT request for updates.
        $response = $this->sendRequestWithFile(
            'PUT',
            '/api/v1/profile',
            $body,
            ['Authorization' => 'Bearer ' . $this->accessToken],
            $files
        );

        $responseBody = json_decode((string) $response->getBody(), true);
        
        // Assert
        $this->assertEquals(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
        $this->assertEquals('success', $responseBody['status']);
        $this->assertArrayHasKey('data', $responseBody);
        $this->assertArrayHasKey('avatar_url', $responseBody['data']);
        $this->assertNotNull($responseBody['data']['avatar_url']);
        $this->assertStringContainsString('.png', $responseBody['data']['avatar_url']);

        // Store for cleanup
        $this->uploadedFiles[] = $responseBody['data']['avatar_url'];
    }
}
