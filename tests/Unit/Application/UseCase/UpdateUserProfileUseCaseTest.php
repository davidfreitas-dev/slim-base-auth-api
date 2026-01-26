<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\UpdateUserProfileRequestDTO;
use App\Application\UseCase\UpdateUserProfileUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\UploadedFileInterface;
use Tests\TestCase;

class UpdateUserProfileUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $pdo;

    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $personRepository;

    private UpdateUserProfileUseCase $updateUserProfileUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);

        $this->updateUserProfileUseCase = new UpdateUserProfileUseCase(
            $this->pdo,
            $this->userRepository,
            $this->personRepository,
            '/tmp/uploads'
        );
    }

    public function testShouldUpdateUserProfileSuccessfully(): void
    {
        $dto = new UpdateUserProfileRequestDTO(1, 'New Name', 'new@email.com', null, null, null);

        $personMock = $this->createMock(Person::class);
        $personMock->expects($this->once())->method('setName')->with('New Name');
        $personMock->expects($this->once())->method('setEmail')->with('new@email.com');

        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($personMock);

        $this->userRepository->method('findById')->with(1)->willReturn($userMock);
        $this->personRepository->method('findByEmail')->with('new@email.com')->willReturn(null);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('update')->with($userMock)->willReturn($userMock);
        $this->pdo->expects($this->once())->method('commit');

        $updatedPerson = $this->updateUserProfileUseCase->execute($dto);
        $this->assertInstanceOf(Person::class, $updatedPerson);
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

        $userPerson = $this->createMock(Person::class);
        $userPerson->method('getId')->willReturn(1);
        $userMock = $this->createMock(User::class);
        $userMock->method('getPerson')->willReturn($userPerson);
        $this->userRepository->method('findById')->with(1)->willReturn($userMock);

        $conflictingPerson = $this->createMock(Person::class);
        $conflictingPerson->method('getId')->willReturn(2);
        $this->personRepository->method('findByEmail')->with('conflict@email.com')->willReturn($conflictingPerson);

        $this->updateUserProfileUseCase->execute($dto);
    }

    public function testShouldRollbackOnUpdateFailure(): void
    {
        $this->expectException(\Exception::class);
        $dto = new UpdateUserProfileRequestDTO(1, 'New Name', null, null, null, null);

        $personMock = $this->createMock(Person::class);
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
