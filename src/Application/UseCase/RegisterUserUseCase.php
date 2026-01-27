<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\RegisterUserRequestDTO;
use App\Application\DTO\UserResponseDTO;
use App\Domain\Entity\Person;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\UserVerification;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\UserVerificationRepositoryInterface;
use App\Domain\ValueObject\CpfCnpj;
use App\Infrastructure\Mailer\EmailVerificationEmailTemplate;
use App\Infrastructure\Mailer\MailerInterface;
use App\Infrastructure\Security\PasswordHasher;
use DateTimeImmutable;
use Exception;
use PDO;
use Ramsey\Uuid\Uuid;

class RegisterUserUseCase
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserVerificationRepositoryInterface $userVerificationRepository,
        private readonly PasswordHasher $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly string $emailVerificationUrl,
        private readonly int $emailVerificationExpire,
        private readonly Role $defaultUserRole,
    ) {
    }

    public function execute(RegisterUserRequestDTO $dto): UserResponseDTO
    {
        // Check if email already exists
        if ($this->personRepository->findByEmail($dto->email)) {
            throw new ConflictException('Este e-mail já está cadastrado.');
        }

        $this->pdo->beginTransaction();

        try {
            $cpfCnpjVO = null;
            // Check if CPF/CNPJ already exists (MOVED INSIDE TRANSACTION)
            if ($dto->cpfcnpj) {
                try {
                    $cpfCnpjVO = CpfCnpj::fromString($dto->cpfcnpj);

                    if ($this->personRepository->findByCpfCnpj($cpfCnpjVO->value())) {
                        throw new ConflictException('Este CPF/CNPJ já está cadastrado.');
                    }
                } catch (ValidationException $e) {
                    throw $e;
                }
            }

            // Create person
            $person = new Person(
                name: $dto->name,
                email: $dto->email,
                phone: $dto->phone,
                cpfcnpj: $cpfCnpjVO,
            );

            $person = $this->personRepository->create($person);

            // Create user
            $hashedPassword = $this->passwordHasher->hash($dto->password);

            $user = new User(
                person: $person,
                password: $hashedPassword,
                role: $this->defaultUserRole,
                isActive: true,
                isVerified: false, // Explicitly set as false, even though it's default
            );

            $createdUser = $this->userRepository->create($user);

            // Generate and store email verification token
            $verificationToken = Uuid::uuid4()->toString();
            $expiresAt = new DateTimeImmutable()->modify(\sprintf('+%d seconds', $this->emailVerificationExpire));

            $userVerification = new UserVerification(
                userId: $createdUser->getId(),
                token: $verificationToken,
                expiresAt: $expiresAt,
            );
            $this->userVerificationRepository->create($userVerification);

            $this->pdo->commit();
        } catch (ConflictException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (ValidationException $e) {
            $this->pdo->rollBack();
            throw $e;
        } catch (Exception $exception) { // Catch any other generic exceptions
            $this->pdo->rollBack();

            throw $exception;
        }

        // Send email verification email
        $verificationLink = \sprintf('%s?token=%s', $this->emailVerificationUrl, $verificationToken);
        $this->mailer->send(
            new EmailVerificationEmailTemplate(
                $person->getEmail(),
                $person->getName(),
                $verificationLink,
            ),
        );

        return new UserResponseDTO(
            id: $createdUser->getId(),
            name: $createdUser->getPerson()->getName(),
            email: $createdUser->getPerson()->getEmail(),
            roleName: $createdUser->getRole()->getName(),
            isActive: $createdUser->isActive(),
            isVerified: $createdUser->isVerified(),
            phone: $createdUser->getPerson()->getPhone(),
            cpfcnpj: $createdUser->getPerson()->getCpfCnpj() ? $createdUser->getPerson()->getCpfCnpj()->value() : null,
        );
    }
}
