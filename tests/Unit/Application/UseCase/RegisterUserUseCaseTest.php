<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\DTO\RegisterUserRequestDTO;
use App\Application\UseCase\RegisterUserUseCase;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\UserVerification;
use App\Domain\Exception\ConflictException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Infrastructure\Mailer\MailerInterface;
use App\Infrastructure\Security\PasswordHasher;
use Exception;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class RegisterUserUseCaseTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $pdo;

    private \PHPUnit\Framework\MockObject\MockObject $personRepository;

    private \PHPUnit\Framework\MockObject\MockObject $userRepository;

    private \PHPUnit\Framework\MockObject\MockObject $userVerificationRepository;

    private \PHPUnit\Framework\MockObject\MockObject $passwordHasher;

    private \PHPUnit\Framework\MockObject\MockObject $mailer;

    private \PHPUnit\Framework\MockObject\MockObject $defaultUserRole;

    private RegisterUserUseCase $registerUserUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->createMock(PDO::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->userVerificationRepository = $this->createMock(UserVerificationRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasher::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->defaultUserRole = $this->createMock(Role::class);

        $this->registerUserUseCase = new RegisterUserUseCase(
            $this->pdo,
            $this->personRepository,
            $this->userRepository,
            $this->userVerificationRepository,
            $this->passwordHasher,
            $this->mailer,
            'http://test.com/verify', // dummy url
            3600, // dummy expiration
            $this->defaultUserRole
        );
    }

    public function testShouldRegisterUserSuccessfullyAndCommitTransaction(): void
    {
        $dto = new RegisterUserRequestDTO('John Doe', 'test@example.com', 'password123', '123456789', '00.000.000/0001-91');
        $this->personRepository->expects($this->once())->method('findByEmail')->willReturn(null);
        $this->personRepository->expects($this->once())->method('findByCpfCnpj')->willReturn(null);
        $this->passwordHasher->expects($this->once())->method('hash')->willReturn('hashed_password');

        $this->pdo->expects($this->once())->method('beginTransaction');

        $personMock = $this->createMock(Person::class);
        $personMock->method('getId')->willReturn(1);
        $this->personRepository->expects($this->once())->method('create')->willReturn($personMock);

        $userMock = $this->createMock(User::class);
        $this->userRepository->expects($this->once())->method('create')->willReturn($userMock);

        $this->userVerificationRepository->expects($this->once())->method('create');
        $this->mailer->expects($this->once())->method('send');

        $this->pdo->expects($this->once())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $user = $this->registerUserUseCase->execute($dto);
        $this->assertInstanceOf(User::class, $user);
    }

    public function testShouldThrowConflictExceptionForExistingEmail(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Este e-mail já está cadastrado.');

        $dto = new RegisterUserRequestDTO('John Doe', 'existing@example.com', 'password123', null, null);
        $this->personRepository->method('findByEmail')->willReturn($this->createMock(Person::class));

        $this->registerUserUseCase->execute($dto);
    }

    public function testShouldThrowConflictExceptionForExistingCpfCnpj(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Este CPF/CNPJ já está cadastrado.');

        $dto = new RegisterUserRequestDTO('John Doe', 'test@example.com', 'password123', null, '00.000.000/0001-91');
        $this->personRepository->method('findByEmail')->willReturn(null);
        $this->personRepository->method('findByCpfCnpj')->willReturn($this->createMock(Person::class));

        $this->registerUserUseCase->execute($dto);
    }

    public function testShouldRollbackTransactionOnPersonCreationFailure(): void
    {
        $this->expectException(Exception::class);

        $dto = new RegisterUserRequestDTO('John Doe', 'test@example.com', 'password123', null, null);
        $this->personRepository->method('findByEmail')->willReturn(null);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->personRepository->expects($this->once())->method('create')->willThrowException(new Exception('DB error'));
        $this->pdo->expects($this->once())->method('rollBack');
        $this->pdo->expects($this->never())->method('commit');

        $this->registerUserUseCase->execute($dto);
    }
}
