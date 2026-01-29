# Project: PHP Base Auth API

## Environment Setup
- **Docker-based development**: All PHP tools run through Docker containers
- **No local Composer**: Composer commands must use Docker
- **PHP Version**: 8.4
- **Framework**: Slim Framework 4.x

## General Instructions

- When you generate new PHP code, follow the existing coding style in the project
- Follow PSR-12 (PHP Standards Recommendations) for code formatting and style
- Use type hints for function parameters and return types whenever possible
- Prefer strict type declarations (declare(strict_types=1);) at the beginning of files
- Use class constants instead of magic strings when appropriate
- Avoid global functions; prefer class methods or namespaced functions

## Documentation

- Ensure all new functions, methods, and classes have PHPDoc comment blocks
- Include @param, @return, @throws, and @var annotations in PHPDoc blocks
- Document the purpose and behavior of complex classes and methods
- Add usage examples in comments when functionality is complex

## Performance and Best Practices

- Prefer composition over inheritance when appropriate
- Use dependency injection (PHP-DI) instead of instantiating objects directly
- Implement caching where appropriate (Redis, Memcached, or file cache)
- Use PSR-4 autoloading for class loading

## Code Structure

- **Layered Architecture**: The project follows a layered architecture inspired by Domain-Driven Design (DDD) and Clean Architecture principles. This separates concerns and makes the application more maintainable and scalable.
  - **Presentation Layer (`src/Presentation`)**: Responsible for handling HTTP requests and responses. It contains API controllers that receive requests and return responses.
  - **Application Layer (`src/Application`)**: Orchestrates the business logic. It contains use cases that are called by the presentation layer.
  - **Domain Layer (`src/Domain`)**: The core of the application. It contains the business entities, value objects, and repository interfaces. This layer is independent of any framework or external dependency.
  - **Infrastructure Layer (`src/Infrastructure`)**: Contains the concrete implementations of the interfaces defined in the domain layer. This includes database repositories, caching services, and other external services.
- **Dependency Injection**: The project uses PHP-DI for dependency injection, which helps to decouple the components.
- **SOLID Principles**: Organize code following SOLID principles.
- **Single Responsibility Principle**: Keep methods small and focused.

## Code Quality

- Use code formatters (PHP-CS-Fixer) to maintain consistent style
- Avoid deep nesting; refactor complex conditionals into separate methods
- Keep cyclomatic complexity low for maintainability
- Always use Constructor Property Promotion to simplify class property declarations.

## Docker Commands Reference

### Composer Commands
```bash
# Install dependencies
docker compose exec api composer install

# Update dependencies
docker compose exec api composer update

# Require new package
docker compose exec api composer require package/name

# Require dev package
docker compose exec api composer require --dev package/name

# Remove package
docker compose exec api composer remove package/name
```

### Testing Commands

All test commands should be executed inside the API container using Composer scripts:

```bash
# Run all tests
docker compose exec api composer test

# Run tests with detailed output (testdox)
docker compose exec api composer test:testdox

# Run only unit tests
docker compose exec api composer test:unit

# Run only integration tests
docker compose exec api composer test:integration

# Run only functional tests (API)
docker compose exec api composer test:functional

# Generate code coverage report
docker compose exec api composer test:coverage

# Run a specific test file (use PHPUnit directly)
docker compose exec api vendor/bin/phpunit tests/Unit/Domain/Entity/UserTest.php
```

### Detailed Test Development Workflow

When developing tests or improving coverage, the following commands and workflow are useful:

1.  **Run a specific test file with text coverage:**
    ```bash
    # Run a single test file and display a text-based coverage summary for a specific source directory.
    # Replace `path/to/YourTest.php` with the actual path to the test file.
    # Replace `src/Your/Module` with the corresponding source directory for coverage filtering.
    docker compose exec api vendor/bin/phpunit tests/Unit/Domain/Entity/YourTest.php --coverage-filter src/Domain/Entity --coverage-text
    ```
    *   **Tip:** If you get a "No filter is configured" warning, ensure `--coverage-filter` is correctly pointing to the *source directory* (e.g., `src/Domain/Entity`), not just the test file.

2.  **Inspect detailed HTML coverage reports:**
    ```bash
    # Generate the full HTML code coverage report (usually into tools/coverage/)
    docker compose exec api composer test:coverage

    # To view the summary for a specific module (e.g., Domain), you can 'cat' the index.html:
    cat tools/coverage/Domain/index.html

    # To inspect line-by-line coverage for a specific class (e.g., ErrorLog), 'cat' its HTML file:
    cat tools/coverage/Domain/Entity/ErrorLog.php.html
    ```
    *   **Note:** Coverage reports are often ignored by `.gitignore`. Using `cat` directly via `run_shell_command` is a reliable way to inspect them within the agent environment.

3.  **Iterative Test Development Cycle:**
    *   **Identify gaps:** Use coverage reports (HTML or text) to find untested lines/methods/classes.
    *   **Read source & existing test:** Understand the code and current test approach.
    *   **Write/Refactor test:** Add new test cases or improve existing ones to cover identified gaps.
    *   **Run specific test with coverage:** Use the command above to quickly verify your changes and new coverage locally.
    *   **Debug:** If tests fail, analyze output. Pay attention to `TypeErrors` which might indicate bugs in the main codebase (as seen with `Person.php`).
    *   **Repeat:** Continue until desired coverage for the component is achieved.

### Code Quality Commands

```bash
# Check code style (dry-run, no changes)
docker compose exec api composer cs-check

# Fix code style automatically
docker compose exec api composer cs-fix

# Run Rector refactoring
docker compose exec api composer rector

# Simulate Rector refactoring (dry-run)
docker compose exec api composer rector:dry
```

## Security
- Never hardcode credentials
- Always use environment variables for sensitive data
- JWT tokens for API authentication
- Input validation and sanitization required
- Always ensure any newly created sensitive files (e.g., `.env` files, private keys, logs, temporary files) are immediately added to `.gitignore` to prevent accidental commit to version control.

## File Structure
```
project/
├── config/                # Configurações da aplicação
│   ├── bootstrap.php
│   ├── container.php      # Injeção de dependências
│   ├── routes.php
│   └── settings.php
├── database/
│   └── schema.sql         # Schema do banco de dados
├── docs/                  # Documentação do projeto
│   ├── API.md
│   └── postman_collection.json
├── public/
│   └── index.php          # Entry point
├── src/
│   ├── Application/       # Casos de uso e lógica de aplicação
│   │   ├── DTO/
│   │   ├── UseCase/
│   │   └── Validation/
│   ├── Domain/            # Lógica de negócio
│   │   ├── Entity/
│   │   ├── Repository/
│   │   └── Exception/
│   ├── Infrastructure/    # Implementações técnicas
│   │   ├── Http/
│   │   ├── Persistence/
│   │   ├── Security/
│   │   └── Mailer/
│   └── Presentation/      # Camada de API
│       └── Api/V1/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Functional/
├── tools/                 # Ferramentas de desenvolvimento
│   ├── .php-cs-fixer.dist.php
│   ├── phpunit.xml
│   └── rector.php
└── composer.json
```

## Important Rules

1. **NEVER suggest running Composer locally** - Always provide Docker commands using `docker compose exec api composer`
2. **Always show complete Docker commands** - Use the container name and proper syntax
3. **Use Composer scripts when available** - Prefer `composer test` over direct PHPUnit calls for common tasks
4. **Consider Docker context** - File permissions, paths, etc.
5. **Provide working code** - Test commands before suggesting
6. **Use environment variables** - Never hardcode configuration
7. **Follow PSR standards** - PSR-4, PSR-12, PSR-7
8. **No Commits or Staging**: Do not use shell commands like git add or git commit. All Git operations (adding files to staging area and committing changes) must be done manually by the user. The assistant should only create, modify, or delete files as requested, but never interact with Git directly.

## When Refactoring
- Maintain existing functionality
- Preserve environment variable usage
- Keep Docker compatibility
- Update composer.json if needed (using `docker compose exec api composer` commands)
- Ensure all configuration files in `tools/` directory are properly referenced