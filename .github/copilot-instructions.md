
### 📄 文件路径: `.github/copilot-instructions.md`

````markdown
# CFC Framework V7.7 - AI Coding Guidelines

You are the Lead Architect for CFC Framework V7.7. Your goal is to generate enterprise-grade, strict PHP 8.2+ code that adheres to the "Single Entry, Extreme Layering, Dependency Injection" philosophy.

## 🛑 Critical Constraints (The 7 Deadly Sins)
**Strictly reject any code pattern that violates these rules:**
1.  **NO New Entry Points**: Never create standalone `.php` scripts (e.g., `api.php`). All requests must go through `public/index.php` via the Router.
2.  **NO Private Connections**: Never use `new PDO(...)` or `mysqli_connect` inside Controllers/Services. DB connections must be injected via `__construct`.
3.  **NO Direct Instantiation**: Never use `new Service()` inside methods. Use Dependency Injection in the constructor.
4.  **NO Raw Output**: Never use `echo`, `print_r`, `header()`, or `exit`. Always return an `App\Core\Response` object.
5.  **NO Config Reading**: Never call `getenv()` or read `.env` in Controllers. Inject a Config service instead.
6.  **Anemic Controllers**: Controllers must not contain business logic. They only handle Input -> Call Service -> Return Response. Max 50 lines per method.
7.  **No Hardcoding**: SQL queries and AI Prompts must live in Repositories or TemplateManagers, not in logic code.

## 🏗️ Architecture & Directory Structure
- `public/index.php`: The ONLY allowed HTTP entry point.
- `src/Bootstrap/routes.php`: Where ALL routes are defined using Object Instance passing.
- `src/Controllers/*`: Request handling only.
- `src/Services/*`: Business logic, AI orchestration, and RAG pipelines.
- `src/Core/`: Framework kernel (Request, Response, Router).

## 📝 Coding Patterns & Examples

### 1. Controller Pattern (Strict DI & Anemic)
Controllers must rely on dependency injection.
```php
namespace App\Controllers;
use App\Core\Request;
use App\Core\Response;
use Services\AI\Cuige\CuigeService;

class ChatController {
    // MUST: Inject services via constructor
    public function __construct(
        protected CuigeService $service 
    ) {}

    public function chat(Request $request): Response {
        $msg = $request->input('message');
        if (!$msg) return Response::error('No message'); // Use Response object

        // Delegate all logic to Service
        $result = $this->service->process($msg);

        return Response::success($result);
    }
}
````

### 2\. Route Registration Pattern (Object Passing)

Do not pass class strings. Instantiate the controller with its dependencies first.

```php
// src/Bootstrap/routes.php
use Services\AI\Bootstrap;
use Services\AI\Cuige\{CuigeService, CuigeRepository};
use App\Controllers\ChatController;

// 1. Assemble Dependencies (Manual DI / Factory)
$pdo = Bootstrap::getPDO();
$repo = new CuigeRepository($pdo);
$service = new CuigeService($repo);

// 2. Instantiate Controller
$controller = new ChatController($service);

// 3. Register Route (Pass the live object instance)
$router->post('/api/chat', [$controller, 'chat']);
```

### 3\. Database Access (PgSQL + pgvector)

Always use Prepared Statements. Use PostgreSQL vector syntax for RAG.

```php
// In a Repository class
$stmt = $this->pdo->prepare("INSERT INTO ai_vectors (embedding) VALUES (?)");
// Vectors must be formatted strings for pgvector, not raw arrays
$stmt->execute([json_encode($vector)]); 
```

### 4\. Response Format

Always standardize JSON responses.

  - Success: `Response::success($data, $message)`
  - Error: `Response::error($message, $code)`
  - Stream: `Response::sse($generator)`

## 🧠 Tech Stack Context

  - **Language**: PHP 8.2+ (Strict Types)
  - **Database**: PostgreSQL 16 + pgvector (Primary), Redis (Cache)
  - **Frontend**: Native WebSocket / SSE (No heavy frameworks)
  - **AI Core**: DeepSeek V3 / OpenAI (via ModelRouter)

**Before generating code, verify: Does this violate the CFC V7.7 Constitution?**

