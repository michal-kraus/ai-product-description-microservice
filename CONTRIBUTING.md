# Contributing

Thank you for your interest in contributing to the Product Description Microservice! This document outlines the conventions and workflows used in this project.

## Prerequisites

- PHP 8.5+
- Composer 2
- Docker & Docker Compose
- Redis (via Docker or locally installed)
- `make` (optional but recommended)

## Getting Started

```bash
# 1. Clone the repository
git clone git@github.com:michal-kraus/ai-product-description-microservice.git
cd ai-product-description-microservice

# 2. Copy environment file
cp .env.example .env.local

# 3. Start infrastructure
make up

# 4. Install dependencies
composer install

# 5. Verify everything works
make check
```

## Development Workflow

### Running the Full Verification Suite

```bash
make check    # Runs: lint → PHPStan → PHPUnit with coverage
```

### Individual Commands

```bash
make test       # PHPUnit tests
make coverage   # PHPUnit with PCOV coverage report
make stan       # PHPStan static analysis (Level 8)
make lint       # YAML lint + Symfony container validation
```

## Coding Standards

### PHP

- **Strict types**: Every PHP file must start with `declare(strict_types=1);`
- **Readonly**: Use `readonly` properties and classes wherever possible
- **Type declarations**: All parameters, return types, and properties must be fully typed
- **PHPStan Level 8**: Code must pass static analysis at the highest level with zero errors

### Architecture

- **Dependency Injection**: All dependencies are injected via constructors
- **Interfaces over implementations**: Program against contracts (`AIClientInterface`), not concrete classes
- **DTOs as Value Objects**: Use immutable `readonly` classes for data transfer
- **Domain exceptions**: Use specific exception classes (e.g., `ProductDescriptionGenerationException`)

### Testing

- **Test naming**: `testIt<DoesExpectedBehavior>` (e.g., `testItGeneratesDescription`)
- **Stubs vs Mocks**: Use `createStub()` when no expectations are needed; `createMock()` only when verifying interactions
- **Coverage target**: Maintain line coverage above 95%
- **Test location**: Unit tests in `tests/Unit/`, functional tests in `tests/Functional/`

## Commit Convention

This project follows [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<optional scope>): <short description>
```

### Allowed Types

| Type | Description |
|---|---|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation changes |
| `style` | Code style (formatting, no logic change) |
| `refactor` | Code refactoring (no feature or fix) |
| `perf` | Performance improvement |
| `test` | Adding or updating tests |
| `build` | Build system or dependencies |
| `ci` | CI/CD pipeline changes |
| `chore` | Maintenance tasks |
| `revert` | Reverting a previous commit |

### Examples

```
feat(ai): add Google Gemini client implementation
fix(cache): resolve TTL not being applied to Redis pool
test(controller): add rate limiting edge case tests
docs: update README with Mermaid architecture diagram
```

## Pull Request Guidelines

1. Create a feature branch from `main`
2. Write tests for any new functionality
3. Ensure `make check` passes with zero errors
4. Keep commits atomic and well-described
5. Update documentation if the change affects the public API or configuration
