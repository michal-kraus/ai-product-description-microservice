# 🛒 Product Description Microservice

🌐 **Language / Język:** **[🇺🇸 English](README.md)** | [🇵🇱 Polski](README.pl.md)

[![CI](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml/badge.svg)](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen?logo=phpstan)](https://phpstan.org/)
[![Coverage](https://img.shields.io/badge/Coverage-99.6%25-brightgreen?logo=codecov)](https://github.com/michal-kraus/ai-product-description-microservice)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-6BA539?logo=openapi-initiative&logoColor=white)](openapi.yaml)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

An asynchronous e-commerce product description generator microservice powered by AI (**Ollama** / **Google Gemini**).

Built as an autonomous, production-grade microservice designed to transform product attributes into marketing copy with caching, rate limiting, and distributed async message queues.

> [!NOTE]
> **Portfolio & Reference Architecture Showcase**
> This repository serves as a production-grade demonstration project showcasing modern **PHP 8.5** and **Symfony 8.1** best practices, clean architecture patterns (Strategy, Factory, Builder, DTO), distributed asynchronous message queues, rate limiting, strict static analysis (PHPStan Level 8), and high test coverage (>99%).

---

## 🏛️ Architecture

```mermaid
flowchart TD
    subgraph Clients ["Input Interfaces"]
        HTTP["REST API (Sync / Async / Health / Docs)"]
        CLI["Symfony CLI (app:generate-description)"]
    end

    subgraph App ["Application Core"]
        Limiter["Rate Limiter (30 req/min)"]
        Controller["ProductDescriptionController"]
        Command["GenerateProductDescriptionCommand"]
        Generator["ProductDescriptionGenerator"]
        JobManager["JobStatusManager"]
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
        RedisQueue[("Redis (Messenger Transport)")]
        OllamaSrv["Ollama Server (Local LLM)"]
        GeminiAPI["Google Gemini API (Cloud LLM)"]
    end

    HTTP --> Limiter --> Controller
    CLI --> Command

    Controller -- "Sync Execution" --> Generator
    Command -- "Sync Execution" --> Generator

    Controller -- "Async Dispatch" --> Bus
    Command -- "Async Dispatch" --> Bus

    Bus --> RedisQueue --> Handler
    Handler -- "Update Status" --> JobManager
    Handler --> Generator

    Generator <-->|"Check / Store Cache (TTL 600s)"| RedisCache
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
- **Sliding Window Rate Limiter** — Sliding window algorithm (30 req/min) with isolated cache storage.
- **Async Queue Processing** — Non-blocking message dispatch via Symfony Messenger over Redis.
- **Multi-layer Caching** — Intelligent Redis caching of generated descriptions (TTL: 600s).

---

## 📖 API Documentation

> 🌐 **Interactive Swagger UI**: Available at **`http://localhost:8000/api/docs`**  
> 📄 **OpenAPI Specification**: [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1)

### Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/health` | Service health check and dependency status (Redis, AI provider) |
| `POST` | `/product/descriptions/sync` | Synchronous product description generation |
| `POST` | `/product/descriptions/async` | Asynchronous job dispatch (returns `job_id`) |
| `GET` | `/product/descriptions/async/{jobId}` | Poll status of an async generation job |
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
  "job_id": "669f1a2b3c4d5",
  "status": "pending"
}
```

#### 3. Poll Async Job Status
```bash
curl http://localhost:8000/product/descriptions/async/669f1a2b3c4d5
```
```json
{
  "job_id": "669f1a2b3c4d5",
  "status": "completed",
  "description": "The Galaxy Smartphone X delivers flagship performance...",
  "error": null
}
```

#### 4. Health Check
```bash
curl http://localhost:8000/health
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
make check       # Runs full verification suite (lint + stan + test with coverage)
make test        # Runs PHPUnit test suite
make coverage    # Runs PHPUnit with PCOV line coverage table
make stan        # Runs PHPStan static analysis (Level 8)
make lint        # Validates YAML files and Dependency Injection container
make up          # Starts Docker containers (Redis, Ollama, App, Worker)
make down        # Stops Docker containers
make worker      # Starts Symfony Messenger async queue consumer
```

---

## 🚀 Quick Start (Local Setup)

### 1. Prerequisites
- PHP 8.5+
- Composer 2
- Docker & Docker Compose
- PCOV PHP extension (optional, for code coverage reports)

### 2. Start Infrastructure
```bash
make up
# or: docker compose up -d
```

### 3. Pull Local AI Model (Ollama)
```bash
docker compose exec ai_local ollama pull qwen2.5:0.5b
```

### 4. Install Dependencies & Configure
```bash
composer install
cp .env .env.local
```

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

The project includes **50 automated tests** (Unit + Functional) with **99.6% code coverage**:

```bash
make check
```

Results:
* **PHPStan Level 8**: `[OK] No errors` (35 files analyzed)
* **PHPUnit 13**: `OK (50 tests, 171 assertions)`
* **Code Coverage**: `99.59% lines covered`

---

## ⚙️ Tech Stack

- **PHP 8.5** — `declare(strict_types=1)`, `readonly` classes, enums, match expressions, constructor promotion
- **Symfony 8.1** — Framework, Messenger, RateLimiter, Cache, HttpClient, Console, Serializer
- **Redis** — description caching, rate limiter storage, Messenger transport
- **Ollama** — self-hosted local AI inference engine
- **Google Gemini API** — cloud LLM provider
- **PHPStan (Level 8)** — maximum strictness static type checking
- **PHPUnit 13 & PCOV** — comprehensive unit and functional testing suite with coverage
- **Docker & Docker Compose** — containerized environment (multi-stage Alpine PHP-FPM)
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
│   ├── HealthCheckController.php         # /health monitoring endpoint
│   └── ProductDescriptionController.php  # REST API endpoints (sync & async)
├── Enum/
│   └── GenerateProductDescriptionMessageStatus.php # State machine enum
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
