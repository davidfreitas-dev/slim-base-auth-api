<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use PDO;
use Faker\Factory;
use Tests\TestCase;
use App\Application\DTO\PersonResponseDTO;
use App\Application\DTO\UpdateUserProfileRequestDTO;
use App\Application\UseCase\UpdateUserProfileUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use PHPUnit\Framework\MockObject\MockObject;

class UpdateUserProfileUseCaseTest extends TestCase
{
    private PDO&MockObject $pdo;
    private UserRepositoryInterface&MockObject $userRepository;
    private PersonRepositoryInterface&MockObject $personRepository;
    private UpdateUserProfileUseCase $updateUserProfileUseCase;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->faker = Factory::create('pt_BR');

        $this->updateUserProfileUseCase = new UpdateUserProfileUseCase(
            $this->pdo,
            $this->userRepository,
            $this->personRepository,
            '/tmp/uploads'
        );
    }

    public function testShouldUpdateUserProfileSuccessfully(): void
    {
        $userId = 1;
        $newName = 'New Name';
        $newEmail = 'new@email.com';
        $newPhone = '11987654321';
        $newCpfCnpj = $this->faker->cpf();
        $newAvatarUrl = '/uploads/1-1234567890.jpg';

        $dto = new UpdateUserProfileRequestDTO(
            userId: $userId,
            name: $newName,
            email: $newEmail,
            phone: $newPhone,
            cpfcnpj: $newCpfCnpj,
            profileImage: null
        );

        /** @var Person&MockObject $personMock */
        $personMock = $this->createMock(Person::class);
        $personMock->method('getId')->willReturn($userId);
        $personMock->method('getName')->willReturn($newName);
        $personMock->method('getEmail')->willReturn($newEmail);
        $personMock->method('getPhone')->willReturn($newPhone);
        $personMock->method('getCpfCnpj')->willReturn(CpfCnpj::fromString($newCpfCnpj));
        $personMock->method('getAvatarUrl')->willReturn($newAvatarUrl);

        $personMock->expects($this->once())->method('setName')->with($newName);
        $personMock->expects($this->once())->method('setEmail')->with($newEmail);
        $personMock->expects($this->once())->method('setPhone')->with($newPhone);
        $personMock->expects($this->once())->method('setCpfCnpj')->with(CpfCnpj::fromString($newCpfCnpj));

        /** @var User&MockObject $userMock */
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($personMock);
        $userMock->expects($this->once())->method('touch');

        $this->userRepository->method('findById')->with($userId)->willReturn($userMock);
        $this->personRepository->method('findByEmail')->with($newEmail)->willReturn(null);
        $this->personRepository->method('findByCpfCnpj')->with(CpfCnpj::fromString($newCpfCnpj)->value())->willReturn(null);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('update')->with($userMock)->willReturn($userMock);
        $this->pdo->expects($this->once())->method('commit');

        $updatedPersonResponseDto = $this->updateUserProfileUseCase->execute($dto);
        $this->assertInstanceOf(PersonResponseDTO::class, $updatedPersonResponseDto);
        $this->assertEquals($userId, $updatedPersonResponseDto->id);
        $this->assertEquals($newName, $updatedPersonResponseDto->name);
        $this->assertEquals($newEmail, $updatedPersonResponseDto->email);
        $this->assertEquals($newPhone, $updatedPersonResponseDto->phone);
        $this->assertEquals(CpfCnpj::fromString($newCpfCnpj)->value(), $updatedPersonResponseDto->cpfcnpj);
        $this->assertEquals($newAvatarUrl, $updatedPersonResponseDto->avatarUrl);
    }

    public function testShouldThrowNotFoundExceptionWhenUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $dto = new UpdateUserProfileRequestDTO(999, 'New Name', null, null, null, null);
        $this->userRepository->method('findById')->with(999)->willReturn(null);

        $this->updateUserProfileUseCase->execute($dto);
    }

    public function testShouldThrowValidationExceptionOnEmailConflict(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email already registered by another user.');

        $dto = new UpdateUserProfileRequestDTO(1, 'User One', 'conflict@email.com', null, null, null);

        /** @var Person&MockObject $userPerson */
        $userPerson = $this->createMock(Person::class);
        $userPerson->method('getId')->willReturn(1);
        
        /** @var User&MockObject $userMock */
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($userPerson);
        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        /** @var Person&MockObject $conflictingPerson */
        $conflictingPerson = $this->createMock(Person::class);
        $conflictingPerson->method('getId')->willReturn(2);
        $this->personRepository->method('findByEmail')->with('conflict@email.com')->willReturn($conflictingPerson);

        $this->updateUserProfileUseCase->execute($dto);
    }

    public function testShouldRollbackOnUpdateFailure(): void
    {
        $this->expectException(\Exception::class);
        $dto = new UpdateUserProfileRequestDTO(1, 'New Name', null, null, null, null);

        /** @var Person&MockObject $personMock */
        $personMock = $this->createMock(Person::class);
        
        /** @var User&MockObject $userMock */
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($personMock);
        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->userRepository->method('update')->will($this->throwException(new \Exception('DB Error')));
        $this->pdo->expects($this->once())->method('rollBack');
        $this->pdo->expects($this->never())->method('commit');

        $this->updateUserProfileUseCase->execute($dto);
    }
}