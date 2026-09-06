# 🛒 Product Description Microservice

🌐 **Language / Język:** **[🇺🇸 English](README.md)** | [🇵🇱 Polski](README.pl.md)

[![CI](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml/badge.svg)](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen?logo=phpstan)](https://phpstan.org/)
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen?logo=codecov)](https://github.com/michal-kraus/ai-product-description-microservice)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-6BA539?logo=openapi-initiative&logoColor=white)](openapi.yaml)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

An asynchronous e-commerce product description generator microservice powered by AI (**Ollama** / **Google Gemini**).

Built as an autonomous, production-oriented reference implementation designed to transform product attributes into marketing copy with caching, rate limiting, and distributed async message queues.

> [!NOTE]
> **Portfolio & Reference Architecture Showcase**
> This repository serves as a production-oriented reference implementation showcasing modern **PHP 8.5** and **Symfony 8.1** best practices, clean architecture patterns (Strategy, Factory, Builder, DTO), distributed asynchronous message queues, rate limiting, strict static analysis (PHPStan Level 8), and comprehensive automated testing.

---

## 🏛️ Architecture

```mermaid
flowchart TD
    subgraph Clients ["Input Interfaces"]
        HTTP["REST API (Sync / Async / Health / Ready / Docs)"]
        CLI["Symfony CLI (app:generate-description)"]
    end

    subgraph WebServer ["Web Server Layer"]
        Nginx["Nginx 1.27 (Reverse Proxy :8000)"]
    end

    subgraph App ["Application Core (PHP-FPM :9000)"]
        Limiter["Rate Limiter (30 req/min API, 120 req/min Status)"]
        Controller["ProductDescriptionController"]
        Command["GenerateProductDescriptionCommand"]
        Generator["ProductDescriptionGenerator"]
        JobManager["JobStatusManager"]
        JobListener["JobFailedListener (Messenger)"]
    end

    subgraph MessengerLayer ["Async Queues (Symfony Messenger)"]
        Bus["Symfony Messenger Bus"]
        Handler["GenerateProductDescriptionMessageHandler"]
    end

    subgraph AIService ["AI Strategy Pattern"]
        Factory["AIClientFactory"]
        Strategy["AIClientInterface"]
        Ollama["OllamaClient (Local)"]
        Gemini["GeminiClient (Cloud API)"]
    end

    subgraph Infra ["Infrastructure & External APIs"]
        RedisCache[("Redis (Cache & Rate Limiter)")]
        Queues[("Message Broker (Redis Queue / RabbitMQ AMQP)")]
        OllamaSrv["Ollama Server (Local LLM)"]
        GeminiAPI["Google Gemini API (Cloud LLM)"]
    end

    HTTP --> Nginx --> Limiter --> Controller
    CLI --> Command

    Controller -- "Sync Execution" --> Generator
    Command -- "Sync Execution" --> Generator

    Controller -- "Async Dispatch (UUIDv7)" --> Bus
    Command -- "Async Dispatch (UUIDv7)" --> Bus

    Bus --> Queues --> Handler
    Handler -- "Update Status (Processing / Completed)" --> JobManager
    Handler --> Generator
    Handler -. "Permanent Failure Event" .-> JobListener --> JobManager

    Generator <-->|"Check / Store Cache (xxh128 Multi-Factor)"| RedisCache
    Generator --> Strategy

    Factory -. "Instantiates" .-> Strategy
    Strategy --> Ollama
    Strategy --> Gemini

    Ollama <--> OllamaSrv
    Gemini <--> GeminiAPI

    Controller -. "Poll Status" .-> JobManager
    JobManager <--> RedisCache
```

### Key Design Patterns & Engineering Highlights

- **Strategy Pattern** — `AIClientInterface` with hot-swappable providers (`OllamaClient`, `GeminiClient`).
- **Factory Pattern** — `AIClientFactory` dynamically resolves provider based on `AI_PROVIDER` environment variable.
- **DTOs (Data Transfer Objects)** — Immutable `readonly` Value Objects (`DescriptionRequest`, `AIResponse`).
- **Builder Pattern** — `PromptBuilder` for customizable, decoupled prompt templates.
- **Sliding Window Rate Limiter** — Sliding window algorithm for both generation (30 req/min) and status polling (120 req/min) with isolated cache storage.
- **Transport-Agnostic Async Queues** — Non-blocking message dispatch via Symfony Messenger with hot-swappable queue drivers: **Redis** or **RabbitMQ (AMQP)** with automatic retry strategy and dead-letter handling.
- **Multi-factor Versioned Caching** — Fast, collision-resistant xxh128 Redis caching of generated descriptions based on cache version, provider, model, prompt, and input features (TTL: 600s).
- **Request Correlation & Observability** — End-to-end distributed tracing via `X-Request-ID` header (UUID v7) propagated across HTTP requests, responses, async Messenger envelopes, and structured log contexts.

---

## 📖 API Documentation

> 🌐 **Interactive Swagger UI**: Available at **`http://localhost:8000/api/docs`**  
> 📄 **OpenAPI Specification**: [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1)

### Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/health` | Lightweight service liveness probe (`{"status": "ok"}`) |
| `GET` | `/ready` | Service readiness check (verifies Redis and AI provider availability) |
| `POST` | `/product/descriptions/sync` | Synchronous product description generation |
| `POST` | `/product/descriptions/async` | Asynchronous job dispatch (returns UUIDv7 `job_id`) |
| `GET` | `/product/descriptions/async/{jobId}` | Poll status of an async generation job (rate-limited) |
| `GET` | `/api/docs` | Interactive Swagger UI documentation |

---

### Request Examples (cURL)

#### 1. Synchronous Generation
```bash
curl -X POST http://localhost:8000/product/descriptions/sync \
  -H "Content-Type: application/json" \
  -d '{"name": "Laptop Pro 16", "features": "16GB RAM, SSD 1TB, 16-inch IPS screen"}'
```
```json
{
  "description": "The Laptop Pro 16 is a high-performance laptop featuring 16GB RAM..."
}
```

#### 2. Asynchronous Job Dispatch
```bash
curl -X POST http://localhost:8000/product/descriptions/async \
  -H "Content-Type: application/json" \
  -d '{"name": "Galaxy Smartphone X", "features": "6.7 AMOLED, 256GB, 108MP camera"}'
```
```json
{
  "job_id": "0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f",
  "status": "pending"
}
```

#### 3. Poll Async Job Status
```bash
curl http://localhost:8000/product/descriptions/async/0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f
```
```json
{
  "job_id": "0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f",
  "status": "completed",
  "description": "The Galaxy Smartphone X delivers flagship performance...",
  "error": null
}
```

#### 4. Health & Readiness Checks
```bash
# Liveness (process is alive)
curl http://localhost:8000/health
```
```json
{
  "status": "ok"
}
```

```bash
# Readiness (dependencies connected)
curl http://localhost:8000/ready
```
```json
{
  "status": "healthy",
  "checks": {
    "redis": { "healthy": true, "details": "connected" },
    "ai_provider": { "healthy": true, "details": "provider: ollama" }
  }
}
```

---

## 💻 CLI Console Command

The microservice includes a Symfony Console CLI command for automation and terminal usage:

```bash
# Synchronous generation in terminal
php bin/console app:generate-description "Mechanical Keyboard" "Red Switches, RGB Backlight"

# Asynchronous dispatch to Messenger queue
php bin/console app:generate-description "Mechanical Keyboard" "Red Switches, RGB" --async
```

---

## 🛠️ Makefile (Developer Experience)

Developer shortcuts for common tasks:

```bash
make help        # Displays available commands
make check       # Runs full verification suite (lint + cs + stan + coverage)
make test        # Runs PHPUnit test suite
make coverage    # Runs PHPUnit with PCOV line coverage table
make stan        # Runs PHPStan static analysis (Level 8)
make lint        # Validates YAML files and Dependency Injection container
make cs          # Checks coding standards (PHP-CS-Fixer, dry-run)
make cs-fix      # Fixes coding standards automatically (PHP-CS-Fixer)
make up          # Starts Docker containers in the background
make down        # Stops Docker containers
make worker      # Starts Symfony Messenger async queue consumer
```

---

## 🚀 Quick Start (Local Setup)

### 1. Prerequisites
- PHP 8.5+ (with `ext-redis` and `ext-amqp`)
- Composer 2
- Docker & Docker Compose
- PCOV PHP extension (optional, for code coverage reports)

### 2. Start Infrastructure
```bash
make up
# or: docker compose up -d
```
> Services started:
> - **Redis** on `6379`
> - **Ollama** on `21434`
> - **RedisInsight** on `5540` (GUI for Redis cache inspection)
> - **RabbitMQ** on `5672` (Management UI: `http://localhost:15672` — user/pass: `guest`/`guest`, requires `COMPOSE_PROFILES=rabbitmq` or `--profile rabbitmq`)

### 3. Pull Local AI Model (Ollama)
```bash
docker compose exec ai_local ollama pull qwen2.5:0.5b
```

### 4. Install Dependencies & Configure
```bash
cp .env.example .env
composer install
```

> **Queue Transport Switching**: In `.env.local`, set `MESSENGER_TRANSPORT_DSN`:
> - **Redis (Default)**: `redis://localhost:6379/messages`
> - **RabbitMQ (AMQP)**: `amqp://guest:guest@localhost:5672/%2f/messages` *(add `COMPOSE_PROFILES=rabbitmq` in `.env.local` to automatically spin up RabbitMQ)*

### 5. Start Application Server
```bash
symfony serve
# or
php -S localhost:8000 -t public/
```

### 6. Start Message Worker (Async Queue)
```bash
make worker
# or: php bin/console messenger:consume async -vv
```

---

## 🧪 Testing & Code Quality

The project includes **104 automated tests** (Unit + Functional) with **100% code coverage**:

```bash
make check
```

Results:
* **PHPStan Level 8**: `[OK] No errors` (42 files analyzed)
* **PHPUnit 13**: `OK (104 tests, 369 assertions)`
* **Code Coverage**: `100.00% lines covered`

---

## ⚙️ Tech Stack

- **PHP 8.5** — `declare(strict_types=1)`, `readonly` classes, enums, match expressions, constructor promotion
- **Symfony 8.1** — Framework, Messenger, RateLimiter, Cache, HttpClient, Console, Serializer
- **RabbitMQ & Redis** — transport-agnostic async message queuing via AMQP/Redis, description caching, rate limiter storage
- **Ollama** — self-hosted local AI inference engine
- **Google Gemini API** — cloud LLM provider
- **PHPStan (Level 8)** — maximum strictness static type checking
- **PHPUnit 13 & PCOV** — comprehensive unit and functional testing suite with coverage
- **Docker & Docker Compose** — containerized environment (multi-stage Alpine PHP-FPM with `amqp` and `redis` extensions)
- **OpenAPI 3.1 & Swagger UI** — interactive API specification

---

## 📁 Project Structure

```
src/
├── AI/
│   ├── Client/
│   │   ├── AIClientInterface.php         # AI Provider contract
│   │   ├── GeminiClient.php              # Google Gemini API implementation
│   │   └── OllamaClient.php              # Local Ollama client implementation
│   ├── DTO/
│   │   ├── AIResponse.php                # Immutable AI response Value Object
│   │   └── DescriptionRequest.php        # Immutable AI request Value Object
│   ├── Factory/
│   │   └── AIClientFactory.php           # AI Provider factory
│   └── Prompt/
│       └── PromptBuilder.php             # Template-based prompt builder
├── Command/
│   └── GenerateProductDescriptionCommand.php # Symfony CLI console command
├── Controller/
│   ├── ApiDocsController.php             # Swagger UI and OpenAPI YAML endpoint
│   ├── HealthCheckController.php         # /health and /ready monitoring endpoints
│   └── ProductDescriptionController.php  # REST API endpoints (sync & async)
├── DTO/
│   └── GenerateProductDescriptionRequest.php # Validated HTTP input request DTO
├── Enum/
│   └── GenerateProductDescriptionMessageStatus.php # State machine enum
├── EventListener/
│   ├── JobFailedListener.php             # Messenger permanent failure listener
│   └── RequestIdListener.php             # Request correlation ID (X-Request-ID) listener
├── Exception/
│   └── ProductDescriptionGenerationException.php   # Domain-specific exception
├── Message/
│   └── GenerateProductDescriptionMessage.php       # Async message DTO
├── MessageHandler/
│   └── GenerateProductDescriptionMessageHandler.php # Async queue consumer
└── Service/
    ├── JobStatusManager.php              # Redis-backed job state manager
    └── ProductDescriptionGenerator.php    # Core description orchestration with cache
```
