# Middleware

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/middleware.md)

Middleware lets you run logic **before** and/or **after** the controller. Common uses: authentication, authorization, logging, session restoration, CSRF protection, request modification.

---

## Table of Contents

- [Structure](#structure)
- [before()](#before)
- [after()](#after)
- [Registering Middleware](#registering-middleware)
- [Middleware Types](#middleware-types)
- [Execution Order](#execution-order)
- [Stopping the Pipeline](#stopping-the-pipeline)
- [Modifying the Response](#modifying-the-response)
- [Dependency Injection](#dependency-injection)
- [Common Patterns](#common-patterns)
- [Common Errors](#common-errors)

---

## Structure

A middleware is a **class** with an optional `before()` and/or `after()` method.

```php
namespace App\Http\Middleware;

use SulCompany\Router\Request;
use SulCompany\Router\Response;

class AuthMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        // runs before the controller
        return true;
    }

    public function after(Request $request, Response $response): void
    {
        // runs after the controller
    }
}
```

**Rules:**

- `before()` **must return `bool`**
- `after()` returns `void`
- A class can implement **both**, **only one**, or **neither** (ignored)

---

## before()

Runs **before** the controller.

### Signature

```php
public function before(Request $request, Response $response): bool;
```

### Return Values

| Return | Effect |
|--------|--------|
| `true` | Continue to the next middleware / controller |
| `false` | **Stop** the pipeline — no controller runs |

### Example: Authentication

```php
final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->auth->check()) {
            if ($request->expectsJson()) {
                $response->json(['error' => 'Unauthenticated'], 401);
            } else {
                $response->redirect('/login');
            }
            return false;  // stop
        }
        return true;  // continue
    }
}
```

### Example: Request Logging

```php
final class LogMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function before(Request $request, Response $response): bool
    {
        $this->logger->info('Request', [
            'method' => $request->method(),
            'path'   => $request->path(),
            'ip'     => $request->ip(),
        ]);
        return true;
    }
}
```

### Example: CSRF Check (POST only)

```php
final class CsrfMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        if (!$request->isMethod('POST')) {
            return true;
        }

        $token = $request->post('_token') ?? $request->header('x-csrf-token');

        if (!$token || $token !== session('_token')) {
            $response->json(['error' => 'Invalid CSRF token'], 419);
            return false;
        }

        return true;
    }
}
```

---

## after()

Runs **after** the controller.

### Signature

```php
public function after(Request $request, Response $response): void;
```

### Return Value

None (void). The `after()` **cannot stop** the pipeline.

### Example: Add Response Header

```php
final class CorsMiddleware
{
    public function after(Request $request, Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE');
    }
}
```

### Example: Response Logging

```php
final class ResponseLogMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function after(Request $request, Response $response): void
    {
        $this->logger->info('Response', [
            'status' => $response->getStatusCode(),
            'path'   => $request->path(),
        ]);
    }
}
```

### Example: Modify HTML (inject a banner)

```php
final class BannerMiddleware
{
    public function after(Request $request, Response $response): void
    {
        $body = $response->getBody();

        if (is_string($body) && str_contains($body, '<body')) {
            $body = str_replace('<body', '<body data-banner="1"', $body);
            $response->html($body, $response->getStatusCode());
        }
    }
}
```

> ⚠️ **Careful:** `after()` runs after the controller, but **before** `send()`. Modifying the body here works.

---

## Registering Middleware

### Global

Runs on **every** request:

```php
$router->addGlobalMiddleware(SessionRestoreMiddleware::class);
```

Multiple at once:

```php
$router->addGlobalMiddleware([
    SessionRestoreMiddleware::class,
    LogMiddleware::class,
]);
```

### Group

Runs on every route in the group:

```php
$router->group('/admin', middleware: [AuthAdminMiddleware::class], callback: function ($r) {
    // ...
});
```

### Route

Runs only on that route:

```php
$router->get('/admin/report', [ReportController::class, 'export'], middleware: [ReportPermissionMiddleware::class]);
```

### Combined

All three apply:

```php
$router->addGlobalMiddleware(LogMiddleware::class);           // global

$router->group('/admin', middleware: [AuthAdminMiddleware::class], callback: function ($r) {  // group
    $r->get('/report', [ReportController::class, 'export'], middleware: [ReportPermissionMiddleware::class]);  // route
});
```

**Execution:**

1. Global before
2. Group before
3. Route before
4. Controller
5. Route after
6. Group after
7. Global after

---

## Middleware Types

| Type | Registration | Scope | When to use |
|------|--------------|-------|-------------|
| **Global** | `addGlobalMiddleware()` | Every request | Session, logging, CORS |
| **Group** | `group(..., middleware: [...])` | Routes in the group | Auth for `/admin`, API key for `/api` |
| **Route** | `get(..., middleware: [...])` | Single route | One-off permissions |

---

## Execution Order

Middlewares run in the **order they are registered**, in two phases:

```
Request
  │
  ├─ Global before #1
  ├─ Global before #2
  ├─ Group before #1
  ├─ Route before #1
  │
  ├─ Controller
  │
  ├─ Route after #1     ← reversed
  ├─ Group after #1     ← reversed
  ├─ Global after #2    ← reversed
  └─ Global after #1    ← reversed
  │
Response
```

**Key rule:** `after()` runs in **reverse order**.

### Why Reverse Order?

Because `after()` is meant to "undo" or "finalize" what `before()` set up. Example:

```php
class TimerMiddleware
{
    private float $start;

    public function before(...): bool { $this->start = microtime(true); return true; }
    public function after(...): void  { /* duration = now - $this->start */ }
}
```

If you have `Timer` wrapping `Auth`, you want `Timer.after` to run **last** to measure the total time.

### Interruption Short-Circuits after()

If a middleware returns `false` from `before()`, **no `after()` runs at all** — for any middleware.

```
Request
  │
  ├─ Global before #1 (true)
  ├─ Global before #2 (false) ← STOPS HERE
  │
  └─ Response (built by #2)
```

No `after()` is called.

---

## Stopping the Pipeline

To stop the pipeline, return `false` from `before()` and **set a Response**.

```php
public function before(Request $request, Response $response): bool
{
    if (!authorized()) {
        $response->json(['error' => 'Forbidden'], 403);
        return false;
    }
    return true;
}
```

### What the Dispatcher Does

When a middleware returns `false`:

1. The pipeline **stops**
2. `$dispatcher->error()` is set to the **Response status code** (or `403` if empty)
3. The Response built by the middleware is **sent**
4. The controller **does not run**

### Reading the Status

```php
$ok = $dispatcher->dispatch();
if (!$ok) {
    $status = $dispatcher->error();  // 403, 401, 302, ...
}
```

### Multiple Response Builders

You can call `->status()` and `->json()`/`->html()` in any order:

```php
$response
    ->status(403)
    ->header('X-Reason', 'no-permission')
    ->json(['error' => 'Forbidden']);
return false;
```

The `Dispatcher` reads `getStatusCode()` from the Response.

---

## Modifying the Response

You can modify the Response **without stopping** the pipeline:

```php
public function before(Request $request, Response $response): bool
{
    $response->header('X-Request-Id', uniqid());
    return true;  // continue
}
```

The header is preserved when the controller returns its own Response.

> ⚠️ **Important:** if the controller **returns** a new `Response`, the injected `$response` mutations are **lost**.
>
> To preserve mutations, the controller must **return the same `$response`** (or not return anything).

### Safe Pattern

```php
// Middleware
public function before(Request $request, Response $response): bool
{
    $response->header('X-App', 'SulCompany');
    return true;
}

// Controller — MUST return the injected response
public function index(Response $response): Response
{
    return $response->html('<h1>Hello</h1>');
}
```

---

## Dependency Injection

Middleware constructors are resolved by the **container** (or autowiring).

```php
final class AuthMiddleware
{
    public function __construct(
        private AuthService $auth,
        private LoggerInterface $logger
    ) {}

    public function before(Request $request, Response $response): bool
    {
        $this->logger->debug('Auth check', ['user' => $this->auth->user()?->id]);
        return $this->auth->check();
    }
}
```

The Router instantiates the middleware **per request** (not singleton) unless the container returns a shared instance.

> 💡 **Tip:** for shared dependencies (logger, DB), register them as `singleton()` in the container.

---

## Common Patterns

### Auth Guard (API + HTML)

```php
final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if ($this->auth->check()) {
            return true;
        }

        if ($request->expectsJson()) {
            $response->json(['error' => 'Unauthenticated'], 401);
        } else {
            $response->redirect('/login', 302);
        }

        return false;
    }
}
```

### Role Guard

```php
final class AdminMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->auth->user()?->hasRole('admin')) {
            $response->json(['error' => 'Forbidden'], 403);
            return false;
        }
        return true;
    }
}
```

### Session Restore

```php
final class SessionRestoreMiddleware
{
    public function __construct(private Session $session) {}

    public function before(Request $request, Response $response): bool
    {
        $this->session->start();
        return true;
    }

    public function after(Request $request, Response $response): void
    {
        $this->session->save();
    }
}
```

### Rate Limit (simplified)

```php
final class RateLimitMiddleware
{
    public function __construct(private RateLimiter $limiter) {}

    public function before(Request $request, Response $response): bool
    {
        $key = $request->ip() . ':' . $request->path();

        if (!$this->limiter->attempt($key, max: 60, window: 60)) {
            $response
                ->status(429)
                ->header('Retry-After', '60')
                ->json(['error' => 'Too Many Requests']);
            return false;
        }

        return true;
    }
}
```

### CORS

```php
final class CorsMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if ($request->isMethod('OPTIONS')) {
            $response->status(204);
            return false;  // don't run the controller for preflight
        }

        return true;
    }
}
```

### Maintenance Mode

```php
final class MaintenanceMiddleware
{
    public function __construct(private Config $config) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->config->get('maintenance.enabled')) {
            return true;
        }

        if ($this->config->get('maintenance.allow_ips') && in_array($request->ip(), $this->config->get('maintenance.allow_ips'))) {
            return true;
        }

        $response->html('<h1>Under maintenance</h1>', 503);
        return false;
    }
}
```

---

## Common Errors

### Middleware Not Running

**Symptom:** you register a middleware but it doesn't execute.

**Possible causes:**

1. **Not registered where you think** — check global/group/route
2. **Typo in class name** — `AuthMiddleware` vs `AuthMidleware` (misspelled)
3. **Class not autoloadable** — verify namespace

### `before()` Return Value Ignored

**Symptom:** pipeline continues even after `return false`.

**Cause:** `before()` doesn't return `bool` (returns `null` or nothing).

**Fix:**

```php
public function before(Request $request, Response $response): bool
{
    // ...
    return true;  // or false — MUST return a bool
}
```

### Response Mutations Lost

**Symptom:** headers set in `before()` don't appear in the final response.

**Cause:** the controller **returns a new Response** instead of mutating the injected one.

**Fix (recommended):** controller should `return $response->...` (the injected one).

```php
public function index(Response $response): Response
{
    return $response->html('<h1>Hello</h1>');  // same object
}
```

### `after()` Not Running

**Symptom:** `after()` doesn't run when a `before()` returned `false`.

**Cause:** by design — if the pipeline is stopped, no `after()` runs.

**Fix:** put cleanup logic in the middleware that **stops** the pipeline.

### Middleware Without `before()`

**Symptom:** class with only `after()` doesn't run `before()`.

**Cause:** by design — classes without `before()` are **ignored** in the before phase.

**Fix:** implement `before()` (can just `return true`).

---

## See Also

- [Routes](routes.md) — registering routes with middleware
- [Groups](groups.md) — group middleware
- [Controllers](controllers.md) — controller return values
- [Error Handling](error-handling.md) — 401, 403, custom error responses
- [Container](container.md) — DI for middleware

---

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/middleware.md)