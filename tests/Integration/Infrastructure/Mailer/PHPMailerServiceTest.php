<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Mailer;

use Faker\Factory;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use App\Domain\Entity\Role;
use App\Domain\Entity\User;
use App\Domain\Entity\Person;
use App\Infrastructure\Mailer\PHPMailerService;
use App\Infrastructure\Persistence\MySQL\RoleRepository;
use App\Infrastructure\Persistence\MySQL\PersonRepository;
use App\Infrastructure\Mailer\EmailVerificationEmailTemplate;
use Tests\Integration\DatabaseTestCase;

class PHPMailerServiceTest extends DatabaseTestCase
{
    private PHPMailerService $mailerService;
    private PersonRepository $personRepository;
    private RoleRepository $roleRepository;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Factory::create('pt_BR');
        
        $logger = new Logger('test-mailer');
        $logger->pushHandler(new NullHandler());

        $this->mailerService = new PHPMailerService(
            $logger,
            $_ENV['MAIL_HOST'],
            (int)$_ENV['MAIL_PORT'],
            $_ENV['MAIL_USERNAME'],
            $_ENV['MAIL_PASSWORD'],
            $_ENV['MAIL_ENCRYPTION'],
            $_ENV['MAIL_FROM_EMAIL'],
            $_ENV['MAIL_FROM_NAME'],
            'http://example.com',
            'Test App'
        );

        $this->personRepository = new PersonRepository(self::$pdo);
        $this->roleRepository = new RoleRepository(self::$pdo);

        $this->cleanMailHog();
    }

    protected function tearDown(): void
    {
        $this->cleanMailHog();
        parent::tearDown();
    }

    private function cleanMailHog(): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://mailhog:8025/api/v1/messages');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function getLatestEmailFromMailHog(): ?object
    {
        $messagesJson = @file_get_contents('http://mailhog:8025/api/v2/messages');
        if ($messagesJson === false) {
            return null;
        }
        $messages = json_decode($messagesJson);
        return $messages->items[0] ?? null;
    }

    public function testSendEmailVerificationEmail(): void
    {
        $person = new Person(name: 'John Doe', email: 'john.doe@example.com');
        $this->personRepository->create($person);
        
        $role = $this->roleRepository->findByName('customer');

        if (!$role instanceof Role) {
            throw new \RuntimeException("Customer role not found in test database. Check DatabaseTestCase seeding.");
        }
        
        $user = new User(person: $person, password: 'password', role: $role);
        $verificationUrl = 'http://example.com/verify?token=123456';
        
        $template = new EmailVerificationEmailTemplate(
            $user->getEmail(),
            $user->getPerson()->getName(),
            $verificationUrl
        );

        $this->mailerService->send($template);

        $email = $this->getLatestEmailFromMailHog();
        
        $this->assertNotNull($email, 'No email found in MailHog');
        
        $this->assertEquals('Verify Your Email Address', $email->Content->Headers->Subject[0]);
        $this->assertEquals('John Doe <john.doe@example.com>', $email->Content->Headers->To[0]);
        $this->assertEquals('Test Sender <no-reply@example.com>', $email->Content->Headers->From[0]);
        $this->assertStringContainsString('Olá, John Doe,', $email->Content->Body);
        $this->assertStringContainsString('Obrigado por se registrar em Test App!', $email->Content->Body);
        $this->assertStringContainsString($verificationUrl, $email->Content->Body);
    }
}
