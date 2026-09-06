# 🛒 Product Description Microservice

🌐 **Język / Language:** [🇺🇸 English](README.md) | **[🇵🇱 Polski](README.pl.md)**

[![CI](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml/badge.svg)](https://github.com/michal-kraus/ai-product-description-microservice/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-brightgreen?logo=phpstan)](https://phpstan.org/)
[![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen?logo=codecov)](https://github.com/michal-kraus/ai-product-description-microservice)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1-6BA539?logo=openapi-initiative&logoColor=white)](openapi.yaml)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Mikroserwis generujący profesjonalne opisy produktów e-commerce z wykorzystaniem AI (**Ollama** / **Google Gemini**).

Zaprojektowany jako niezależna, zorientowana produkcyjnie implementacja referencyjna w architekturze mikroserwisowej — przyjmuje nazwę i cechy produktu, a zwraca gotowy opis marketingowy wygenerowany przez model językowy, z obsługą cache'owania, rate-limiterem i kolejkami asynchronicznymi.

> [!NOTE]
> **Projekt referencyjny / Portfolio Showcase**
> To repozytorium stanowi demonstracyjny mikroserwis zorientowany produkcyjnie, prezentujący najlepsze praktyki w **PHP 8.5** i **Symfony 8.1**, czystą architekturę (wzorce Strategy, Factory, Builder, DTO), asynchroniczne kolejki wiadomości, rate limiting, rygorystyczną analizę statyczną (PHPStan Level 8) oraz wszechstronne testy automatyczne.

---

## 🏛️ Architektura

```mermaid
flowchart TD
    subgraph Clients ["Interfejsy wejściowe"]
        HTTP["REST API (Sync / Async / Health / Ready / Docs)"]
        CLI["Symfony CLI (app:generate-description)"]
    end

    subgraph WebServer ["Serwer Web"]
        Nginx["Nginx 1.27 (Reverse Proxy :8000)"]
    end

    subgraph App ["Warstwa Aplikacji (PHP-FPM :9000)"]
        Limiter["Rate Limiter (30 req/min API, 120 req/min Status)"]
        Controller["ProductDescriptionController"]
        Command["GenerateProductDescriptionCommand"]
        Dispatcher["ProductDescriptionJobDispatcher"]
        Generator["ProductDescriptionGenerator"]
        JobManager["JobStatusManager"]
        JobListener["JobFailedListener (Messenger)"]
    end

    subgraph MessengerLayer ["Kolejki Asynchroniczne (Messenger)"]
        Bus["Symfony Messenger Bus"]
        Handler["GenerateProductDescriptionMessageHandler"]
    end

    subgraph AIService ["AI Strategy Pattern"]
        Factory["AIClientFactory"]
        Strategy["AIClientInterface"]
        Ollama["OllamaClient"]
        Gemini["GeminiClient"]
    end

    subgraph Infra ["Infrastruktura & Zewnętrzne API"]
        RedisCache[("Redis (Cache & Rate Limiter)")]
        LockStore[("Symfony Lock (Rozproszony Mutex)")]
        Queues[("Message Broker (Kolejka Redis / RabbitMQ AMQP)")]
        OllamaSrv["Ollama Server (Local LLM)"]
        GeminiAPI["Google Gemini API (Cloud LLM)"]
    end

    HTTP --> Nginx --> Limiter --> Controller
    CLI --> Command

    Controller -- "Tryb synchroniczny" --> Generator
    Command -- "Tryb synchroniczny" --> Generator

    Controller -- "Zlecenie asynchroniczne" --> Dispatcher
    Command -- "Zlecenie asynchroniczne" --> Dispatcher
    Dispatcher -- "Kolejkuje (UUIDv7)" --> Bus
    Dispatcher --> JobManager

    Bus --> Queues --> Handler
    Handler -- "Aktualizacja statusu (Processing / Completed)" --> JobManager
    Handler --> Generator
    Handler -. "Zdarzenie ostatecznego błędu" .-> JobListener --> JobManager

    Generator <-->|"Sprawdź / Zapisz cache (xxh128 Multi-Factor)"| RedisCache
    Generator --> Strategy

    Factory -. "Tworzy instancję" .-> Strategy
    Strategy --> Ollama
    Strategy --> Gemini

    Ollama <--> OllamaSrv
    Gemini <--> GeminiAPI

    Controller -. "Odpytanie o status" .-> JobManager
    JobManager <-->|"Persystencja stanu"| RedisCache
    JobManager <-->|"Atomowa blokada"| LockStore
```

### Kluczowe wzorce i mechanizmy

- **Strategy & DIP Patterns** — `AIClientInterface` z wymiennymi implementacjami (`OllamaClient`, `GeminiClient`), `JobStatusManagerInterface` odsprzęgający magazyn stanu oraz `PromptBuilderInterface` umożliwiający elastyczną zmianę szablonów i strategii promptowania.
- **Factory Pattern** — `AIClientFactory` tworzy klienta na podstawie zmiennej środowiskowej `AI_PROVIDER`.
- **DTO i Kontrakty API** — `DescriptionRequest`, `AIResponse` jako czyste, niemutowalne obiekty domenowe (readonly Value Objects) oraz dedykowane DTO odpowiedzi API (`SyncDescriptionResponse`, `AsyncJobCreatedResponse`, `AsyncJobStatusResponse`, `ApiErrorResponse`) zgodne ze specyfikacją OpenAPI 3.1.
- **Współbieżność i Rozproszone Blokady (Locking)** — integracja `Symfony Lock` z `LockFactory` gwarantująca bezwzględną atomowość operacji read-modify-write w `JobStatusManager` w środowisku z wieloma workerami.
- **Ujednolicona Orkiestracja Zadań (Job Dispatcher)** — `ProductDescriptionJobDispatcher` hermetyzuje generowanie UUIDv7, inicjalizację stanu w cache, dispatch do Messengera oraz obsługę awarii dla API i CLI.
- **Sliding Window Rate Limiter** — ochrona API generowania (30 req/min) i odpytywania o status (120 req/min) z dedykowaną pulą cache i sparametryzowaną weryfikacją w kontrolerze.
- **Transport-Agnostic Async Queues** — asynchroniczne kolejki wiadomości przez Symfony Messenger z możliwością wyboru brokera: **Redis** lub **RabbitMQ (AMQP)** wraz ze strategią ponowień (retry strategy) oraz dead-letter handling.
- **Wieloskładnikowy Cache z Wersjonowaniem** — szybkie, odporne na kolizje hashowanie xxh128 w Redis dla wygenerowanych opisów w oparciu o wersję cache, providera, model, prompt i cechy (TTL: 600s).
- **Korelacja żądań i obserwowalność (Observability)** — śledzenie żądań end-to-end za pomocą nagłówka `X-Request-ID` (UUID v7), propagowanego w nagłówkach HTTP, kopertach asynchronicznych wiadomości Messengera oraz w logach.

---

## 📖 Dokumentacja API

> 🌐 **Interaktywny Swagger UI**: dostępny pod adresem **`http://localhost:8000/api/docs`**  
> 📄 **Plik specyfikacji**: [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1)

### Uwierzytelnianie

Mikroserwis wykorzystuje natywne, bezstanowe uwierzytelnianie Symfony `access_token` (Bearer). Chronione endpointy wymagają przekazania poprawnego tokenu w nagłówku:
- **Nagłówek Authorization**: `Authorization: Bearer <API_KEY>`

Klucze konfigurowane są przez zmienną środowiskową `APP_API_KEYS` w formacie mapowania `klient:klucz` (np. `ecommerce-app:secret-key-1,magazyn:secret-key-2`). Limity zapytań (rate limits) są naliczane i izolowane niezależnie dla każdego uwierzytelnionego identyfikatora klienta.

### Endpointy

| Metoda | Ścieżka | Auth | Opis |
|---|---|---|---|
| `GET` | `/health` | Publiczny | Szybki liveness probe serwisu (`{"status": "ok"}`) |
| `GET` | `/ready` | Publiczny | Sprawdzenie gotowości i zależności (Redis, AI provider) |
| `POST` | `/product/descriptions/sync` | Chroniony | Synchroniczne wygenerowanie opisu produktu |
| `POST` | `/product/descriptions/async` | Chroniony | Zlecenie asynchronicznego generowania (zwraca UUIDv7 `job_id`) |
| `GET` | `/product/descriptions/async/{jobId}` | Chroniony | Odpytanie o status zadania asynchronicznego (rate-limited) |
| `GET` | `/api/docs` | Publiczny | Interaktywna dokumentacja Swagger UI |

---

### Przykłady zapytań (cURL)

#### 1. Synchroniczne generowanie opisu
```bash
curl -X POST http://localhost:8000/product/descriptions/sync \
  -H "Authorization: Bearer default-test-api-key-12345" \
  -H "Content-Type: application/json" \
  -d '{"name": "Laptop Pro 16", "features": "16GB RAM, SSD 1TB, ekran 16 cali IPS"}'
```
```json
{
  "description": "Laptop Pro 16 to wydajny komputer przenośny z 16GB RAM..."
}
```

#### 2. Asynchroniczne zlecenie zadania
```bash
curl -X POST http://localhost:8000/product/descriptions/async \
  -H "Authorization: Bearer default-test-api-key-12345" \
  -H "Content-Type: application/json" \
  -d '{"name": "Smartfon Galaxy X", "features": "Ekran 6.7 AMOLED, 256GB, aparat 108MP"}'
```
```json
{
  "job_id": "0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f",
  "status": "pending"
}
```

#### 3. Sprawdzenie statusu zadania
```bash
curl http://localhost:8000/product/descriptions/async/0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f \
  -H "Authorization: Bearer default-test-api-key-12345"
```
```json
{
  "job_id": "0195669f-1a2b-7c4d-8e5f-6a7b8c9d0e1f",
  "status": "completed",
  "description": "Smartfon Galaxy X to flagowy model...",
  "error": null
}
```

#### 4. Health & Readiness Checks
```bash
# Liveness (sprawdzenie działania procesu PHP)
curl http://localhost:8000/health
```
```json
{
  "status": "ok"
}
```

```bash
# Readiness (sprawdzenie dostępności zależności)
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

## 💻 Konsola CLI

Poza REST API mikroserwis oferuje komendę Symfony CLI do generowania opisów:

```bash
# Synchroniczne wygenerowanie opisu w terminalu
php bin/console app:generate-description "Klawiatura Mechaniczna" "Przełączniki Red, podświetlenie RGB"

# Asynchroniczne wysłanie zadania do kolejki Messenger
php bin/console app:generate-description "Klawiatura Mechaniczna" "Przełączniki Red, RGB" --async
```

---

## 🛠️ Makefile (Developer Experience)

Dla wygody programisty przygotowano zestaw skrótów `make`:

```bash
make help        # Wyświetla listę wszystkich dostępnych poleceń
make check       # Uruchamia pełny zestaw weryfikacyjny (lint + cs + stan + coverage)
make test        # Uruchamia testy PHPUnit
make coverage    # Uruchamia testy z tabelą pokrycia kodu (PCOV)
make stan        # Uruchamia analizę statyczną PHPStan (Level 8)
make lint        # Waliduje pliki YAML i kontener Dependency Injection
make cs          # Sprawdza standardy kodowania (PHP-CS-Fixer, dry-run)
make cs-fix      # Automatycznie naprawia standardy kodowania (PHP-CS-Fixer)
make up          # Uruchamia kontenery Dockera w tle
make down        # Zatrzymuje kontenery Dockera
make worker      # Uruchamia konsumenta wiadomości Messenger (async)
```

---

## 🚀 Szybki start (Uruchomienie lokalne)

### 1. Wymagania
- PHP 8.5+ (z rozszerzeniami `ext-redis` i `ext-amqp`)
- Composer 2
- Docker & Docker Compose
- Rozszerzenie PHP PCOV (opcjonalnie, do raportu code coverage)

### 2. Uruchomienie infrastruktury
```bash
make up
# lub: docker compose up -d
```
> Uruchomione usługi:
> - **Redis** na porcie `6379`
> - **Ollama** na porcie `21434`
> - **RedisInsight** na porcie `5540` (narzędzie GUI do podglądu danych w Redis)
> - **RabbitMQ** na porcie `5672` (Panel zarządzania UI: `http://localhost:15672` — login/hasło: `guest`/`guest`, wymaga `COMPOSE_PROFILES=rabbitmq` lub `--profile rabbitmq`)

### 3. Pobranie lokalnego modelu AI (Ollama)
```bash
docker compose exec ai_local ollama pull qwen2.5:0.5b
```

### 4. Instalacja zależności i konfiguracja
```bash
cp .env.example .env
composer install
```

> **Wybór brokera kolejek**: W pliku `.env.local` ustaw zmienną `MESSENGER_TRANSPORT_DSN`:
> - **Redis (Domyślnie)**: `redis://localhost:6379/messages`
> - **RabbitMQ (AMQP)**: `amqp://guest:guest@localhost:5672/%2f/messages` *(dodaj `COMPOSE_PROFILES=rabbitmq` w `.env.local`, aby Docker automatycznie uruchomił kontener RabbitMQ)*

### 5. Uruchomienie serwera aplikacji
```bash
symfony serve
# lub
php -S localhost:8000 -t public/
```

### 6. Uruchomienie workera (kolejki async)
```bash
make worker
# lub: php bin/console messenger:consume async -vv
```

---

## 🧪 Testy i jakość kodu

Projekt posiada **123 testy automatyczne** (Unit + Functional) ze **100% pokryciem kodu**:

```bash
make check
```

Wyniki:
* **PHPStan Poziom 8**: `[OK] No errors` (56 przeanalizowanych plików)
* **PHPUnit 13**: `OK (123 tests, 447 assertions)`
* **Pokrycie kodu**: `100.00% linii pokrytych`

---

## ⚙️ Stos technologiczny

- **PHP 8.5** — `declare(strict_types=1)`, `readonly` classes, enums, match expressions, constructor promotion
- **Symfony 8.1** — Framework, Messenger, RateLimiter, Cache, HttpClient, Console, Serializer, Lock
- **RabbitMQ & Redis** — transport-agnostic async message queuing przez AMQP/Redis, cache opisów produktów, rate limiter storage, rozproszony mutex
- **Ollama** — lokalny serwer AI (self-hosted LLM)
- **Google Gemini API** — chmurowy dostawca modeli LLM
- **PHPStan (Level 8)** — maksymalny poziom statycznej analizy typów
- **PHPUnit 13 & PCOV** — zestaw testów jednostkowych i funkcjonalnych z pomiarem pokrycia
- **Docker & Docker Compose** — konteneryzacja środowiska (multi-stage Alpine PHP-FPM z rozszerzeniami `amqp` i `redis`)
- **OpenAPI 3.1 & Swagger UI** — standard dokumentacji API

---

## 📁 Struktura projektu

```
src/
├── AI/
│   ├── Client/
│   │   ├── AIClientInterface.php         # Kontrakt dla providerów AI
│   │   ├── GeminiClient.php              # Klient Google Gemini API
│   │   └── OllamaClient.php              # Klient lokalnego Ollama
│   ├── DTO/
│   │   ├── AIResponse.php                # Niemutowalna odpowiedź AI
│   │   └── DescriptionRequest.php        # Niemutowalne żądanie do AI
│   ├── Factory/
│   │   └── AIClientFactory.php           # Fabryka instancjonująca providera
│   └── Prompt/
│       ├── PromptBuilder.php             # Budowniczy promptów z szablonów
│       └── PromptBuilderInterface.php    # Kontrakt budowy promptów
├── Command/
│   └── GenerateProductDescriptionCommand.php # Konsolowa komenda CLI
├── Controller/
│   ├── ApiDocsController.php             # Swagger UI oraz specyfikacja OpenAPI
│   ├── HealthCheckController.php         # Endpointy monitoringu /health i /ready
│   └── ProductDescriptionController.php  # REST API endpointy (sync & async)
├── DTO/
│   ├── GenerateProductDescriptionRequest.php # Walidowany obiekt DTO żądania HTTP
│   └── Response/
│       ├── ApiErrorResponse.php          # Ustandaryzowane DTO odpowiedzi z błędem
│       ├── AsyncJobCreatedResponse.php   # DTO potwierdzenia przyjęcia zlecenia async
│       ├── AsyncJobStatusResponse.php    # DTO odpytywania o status zadania async
│       └── SyncDescriptionResponse.php   # DTO synchronicznej odpowiedzi z opisem
├── Enum/
│   └── GenerateProductDescriptionMessageStatus.php # Maszyna stanów zadania
├── EventListener/
│   ├── JobFailedListener.php             # Listener trwałych błędów workera Messengera
│   └── RequestIdListener.php             # Listener korelacji żądań HTTP (X-Request-ID)
├── Exception/
│   ├── JobDispatchException.php                  # Wyjątek błędu kolejkowania zadania
│   └── ProductDescriptionGenerationException.php # Dedykowany wyjątek domenowy generowania AI
├── Message/
│   └── GenerateProductDescriptionMessage.php       # DTO wiadomości asynchronicznej
├── MessageHandler/
│   └── GenerateProductDescriptionMessageHandler.php # Konsument wiadomości kolejki
└── Service/
    ├── JobStatusManager.php                      # Współbieżny menedżer stanu zadań (Symfony Lock)
    ├── JobStatusManagerInterface.php             # Kontrakt zarządzania cyklem życia zadań
    ├── ProductDescriptionGenerator.php           # Główna orkiestracja generowania z cache
    ├── ProductDescriptionJobDispatcher.php        # Reużywalny serwis orkiestracji asynchronicznej
    └── ProductDescriptionJobDispatcherInterface.php # Kontrakt dispatchera zadań
```
