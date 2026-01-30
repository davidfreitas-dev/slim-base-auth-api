<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\UseCase\DeleteUserUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\JwtService;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;

    private PersonRepositoryInterface&MockObject $personRepository;

    private JwtService&MockObject $jwtService;

    private PDO&MockObject $pdo;

    private DeleteUserUseCase $deleteUserUseCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->personRepository = $this->createMock(PersonRepositoryInterface::class);
        $this->jwtService = $this->createMock(JwtService::class);
        $this->pdo = $this->createMock(PDO::class);

        $this->deleteUserUseCase = new DeleteUserUseCase(
            $this->userRepository,
            $this->personRepository,
            $this->jwtService,
            $this->pdo
        );
    }

    public function testShouldDeleteUserSuccessfully(): void
    {
        $userId = 1;
        
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->with($userId)->willReturn($user);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('delete')->with($userId);
        $this->personRepository->expects($this->once())->method('delete')->with($userId);
        $this->jwtService->expects($this->once())->method('invalidateAllUserRefreshTokens')->with($userId);
        $this->pdo->expects($this->once())->method('commit');
        $this->pdo->expects($this->never())->method('rollBack');

        $this->deleteUserUseCase->execute($userId);
    }

    public function testShouldThrowNotFoundExceptionIfUserDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);
        $userId = 999;
        $this->userRepository->method('findById')->with($userId)->willReturn(null);
        $this->deleteUserUseCase->execute($userId);
    }

    public function testShouldRollbackOnFailure(): void
    {
        $this->expectException(\Exception::class);

        $userId = 1;
        
        /** @var User&MockObject $user */
        $user = $this->createMock(User::class);
        $this->userRepository->method('findById')->with($userId)->willReturn($user);

        $this->pdo->expects($this->once())->method('beginTransaction');
        $this->userRepository->method('delete')->will($this->throwException(new \Exception('DB error')));
        $this->pdo->expects($this->once())->method('rollBack');
        $this->pdo->expects($this->never())->method('commit');

        $this->deleteUserUseCase->execute($userId);
    }
}