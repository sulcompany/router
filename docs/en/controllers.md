# Controllers

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/controllers.md)

Controllers handle the logic for a route. The Router supports two handler types: **closures** and **class methods**. This page explains both, plus dependency injection and parameter resolution.

---

## Table of Contents

- [Handler Types](#handler-types)
- [Closures](#closures)
- [Class Methods](#class-methods)
- [Dependency Injection](#dependency-injection)
- [Parameter Resolution](#parameter-resolution)
- [Return Values](#return-values)
- [Response Mutation vs Return](#response-mutation-vs-return)
- [Common Patterns](#common-patterns)
- [Common Errors](#common-errors)

---

## Handler Types

| Type | Example | DI support | Cacheable |
|------|---------|------------|-----------|
| **Closure** | `fn($req, $res) => ...` | ❌ (only `$req`, `$res`, `$params`) | ❌ (skipped in cache) |
| **Class method** | `[UserController::class, 'show']` | ✅ (full DI) | ✅ |

**Recommendation:** use **class methods** for anything non-trivial. Use **closures** for tiny endpoints (health checks, ping).

---

## Closures

A closure receives **three arguments**:

```php
$router->get('/health', function (Request $request, Response $response, array $params) {
    return $response->json(['status' => 'ok']);
});
```

### Argument Table

| Argument | Type | Description |
|----------|------|-------------|
| `$request` | `Request` | The current request |
| `$response` | `Response` | The current response (already empty) |
| `$params` | `array` | Route parameters (`{id}` → `['id' => '42']`) |

### Minimal Examples

```php
// No arguments
$router->get('/ping', fn() => (new Response())->text('pong'));

// Request only
$router->get('/who', fn($request) => (new Response())->text($request->ip()));

// Full
$router->get('/user/{id}', function ($request, $response, $params) {
    return $response->json(['id' => $params['id']]);
});
```

### Limitations

- **No dependency injection** — only `$request`, `$response`, `$params`
- **Not cacheable** — closures can't be `var_export`-ed. Routes using closures are **skipped** in the route cache.

---

## Class Methods

Pass an array `[ClassName::class, 'method']`:

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
```

```php
class UserController
{
    public function show(string $id, Response $response): Response
    {
        return $response->json(['id' => $id]);
    }
}
```

### What the Router Does

1. **Resolves the class** — via container or autowiring
2. **Checks the method exists** — throws 405 if not
3. **Resolves method parameters** — by type, then by name
4. **Invokes the method**
5. **Captures the return value** — if `Response`, uses it; otherwise uses the injected `$response`

### Static Methods

```php
$router->get('/ping', [HealthController::class, 'ping']);
```

```php
class HealthController
{
    public static function ping(Response $response): Response
    {
        return $response->json(['status' => 'ok']);
    }
}
```

Works the same way. The Router uses `ReflectionMethod::invokeArgs()`, which handles both static and instance methods.

### Abstract / Interface Controllers

**Not supported.** The Router must instantiate the controller. Abstract classes and interfaces cannot be instantiated.

### Constructor Injection

```php
class UserController
{
    public function __construct(
        private UserRepository $repository,
        private LoggerInterface $logger
    ) {}

    public function show(string $id, Response $response): Response
    {
        $this->logger->info("Fetching user {$id}");
        $user = $this->repository->find($id);

        if (!$user) {
            return $response->json(['error' => 'Not Found'], 404);
        }

        return $response->json($user);
    }
}
```

The container resolves `UserRepository` and `LoggerInterface` automatically — **if registered** or **autowireable**.

---

## Dependency Injection

The Router resolves constructor and method parameters **two ways**:

### 1. By Type

If the parameter has a **non-builtin** type, the Router resolves it:

| Type | Resolved to |
|------|-------------|
| `Request` | Current `Request` instance |
| `Response` | Current `Response` instance |
| Class in container | Container instance |
| Autowireable class | New instance via reflection |

### 2. By Name

If the parameter name matches a **route parameter**, it's injected:

```php
$router->get('/user/{id}', [UserController::class, 'show']);
```

```php
public function show(string $id): Response  // $id comes from {id}
```

### Resolution Order

For each parameter, the Router tries:

1. **Type match** → `Request`, `Response`, container, autowiring
2. **Name match** → route parameter with the same name
3. **Request data** → `$request->all($name)`
4. Otherwise → **`null`** (silent)

### Examples

```php
// Type-based
public function index(Request $request): Response

// Name-based
public function show(string $id): Response

// Mixed
public function update(Request $request, string $id): Response

// With DI
public function store(CreateUserRequest $dto, Response $response): Response
```

### Fallback to Request Data

If a parameter is not a type and not a route param, the Router looks in the request data (GET, POST, JSON):

```php
$router->post('/search', [SearchController::class, 'search']);
```

```php
public function search(string $q): Response  // $q comes from ?q=... or POST
```

### Type Priority

When a parameter has a type AND the name matches a route param, **the type wins**:

```php
public function show(Request $id): Response
// ❌ $id will be the Request, not the route param
```

**Avoid this.** Use clear names:

```php
public function show(string $id, Request $request): Response
```

---

## Parameter Resolution

### Builtin Types

| Type | Resolution |
|------|------------|
| `string` | Route param or request data |
| `int` | Route param (as string) or request data |
| `float` | Route param (as string) or request data |
| `bool` | Route param or request data |
| `array` | Route param (wildcard) or request data |

> ⚠️ **Note:** The Router does **not** cast types. If `{id}` is `"42"` (string) and the parameter is `int $id`, PHP will throw a `TypeError` in strict mode.

**Fix:** declare the parameter as `string` and cast manually:

```php
public function show(string $id): Response
{
    $id = (int) $id;
    // ...
}
```

### Class Types

| Type | Resolution |
|------|------------|
| `Request` | Current request |
| `Response` | Current response |
| Any class | Container → autowiring |
| `?Class` (nullable) | Same as above, or `null` |

### Wildcard Parameters

`{path*}` returns an **array**:

```php
$router->get('/files/{path*}', [FileController::class, 'serve']);
```

```php
public function serve(array $path): Response
{
    $file = implode('/', $path);
    // ...
}
```

### Missing Parameters

If a parameter cannot be resolved, it's **`null`**:

```php
public function show(string $id, string $missing): Response
// $missing will be null (or PHP throws in strict mode)
```

**Tip:** declare nullable types or provide defaults.

---

## Return Values

### Returning a Response

**Recommended.** The Router uses the returned Response:

```php
public function index(Response $response): Response
{
    return $response->html('<h1>Hello</h1>');
}
```

### Returning Nothing

If the method returns `void` or nothing, the **injected `$response`** is used:

```php
public function index(Response $response): void
{
    $response->html('<h1>Hello</h1>');
}
```

**Both work.** Returning is cleaner and easier to test.

### Returning a Different Response

The controller can ignore the injected `$response` and create its own:

```php
public function index(): Response
{
    return (new Response())->json(['ok' => true]);
}
```

**But:** any middleware that mutated the injected `$response` **loses those mutations**.

### Non-Response Returns

If the method returns something that is **not** a `Response`, the return value is **ignored**:

```php
public function index(Response $response): array
{
    return ['ok' => true];  // ❌ ignored
}
```

**Always return a `Response`** (or nothing).

---

## Response Mutation vs Return

### The Rule

- If the controller **returns a `Response`** → that Response is used
- If the controller **returns nothing** → the injected `$response` is used

### Middleware Impact

```php
// Middleware sets a header
public function before(Request $request, Response $response): bool
{
    $response->header('X-App', 'SulCompany');
    return true;
}
```

**Case A — controller returns the injected response:**

```php
public function index(Response $response): Response
{
    return $response->html('<h1>Hello</h1>');
}
```

**Result:** header `X-App: SulCompany` is **preserved**. ✅

**Case B — controller returns a NEW response:**

```php
public function index(): Response
{
    return (new Response())->html('<h1>Hello</h1>');
}
```

**Result:** header `X-App` is **LOST**. ❌

**Rule:** always `return $response->...` with the **injected** response, unless you know what you're doing.

---

## Common Patterns

### RESTful Resource Controller

```php
class PostController
{
    public function __construct(private PostRepository $posts) {}

    public function index(Response $response): Response
    {
        return $response->json($this->posts->all());
    }

    public function show(string $id, Response $response): Response
    {
        $post = $this->posts->find($id);

        if (!$post) {
            return $response->json(['error' => 'Not Found'], 404);
        }

        return $response->json($post);
    }

    public function store(Request $request, Response $response): Response
    {
        $post = $this->posts->create($request->json());
        return $response->json($post, 201);
    }

    public function update(string $id, Request $request, Response $response): Response
    {
        $post = $this->posts->update($id, $request->json());
        return $response->json($post);
    }

    public function destroy(string $id, Response $response): Response
    {
        $this->posts->delete($id);
        return $response->status(204);
    }
}
```

### Form + API Response

```php
public function store(Request $request, Response $response): Response
{
    $name  = $request->post('name');
    $email = $request->post('email');

    $user = $this->users->create($name, $email);

    if ($request->expectsJson()) {
        return $response->json(['user' => $user], 201);
    }

    return $response->redirect('/users/' . $user->id);
}
```

### File Download

```php
public function download(string $id, Response $response): Response
{
    $file = $this->files->find($id);

    return $response
        ->header('Content-Type', $file->mimeType())
        ->header('Content-Disposition', "attachment; filename=\"{$file->name()}\"")
        ->text($file->contents());
}
```

### Redirect Back

```php
public function store(Request $request, Response $response): Response
{
    $this->users->create($request->post());

    return $response->back();  // redirect to HTTP_REFERER
}
```

---

## Common Errors

### Class Not Found

**Symptom:** `Error: Class "UserController" not found`.

**Cause:** wrong namespace or the class doesn't exist.

**Fix:** verify the namespace. Use `::class` constant:

```php
$router->get('/users', [\App\Http\Controllers\UserController::class, 'index']);
```

### Method Not Found

**Symptom:** 405 Method Not Allowed.

**Cause:** the method doesn't exist on the controller.

**Fix:** verify spelling and visibility (`public`).

### Cannot Instantiate Abstract Class

**Symptom:** `Error: Cannot instantiate abstract class Foo`.

**Cause:** the container tries to `new` an abstract class or interface.

**Fix:** register the concrete class in the container:

```php
$container->bind(SomeInterface::class, SomeConcrete::class);
```

### Unresolvable Parameter

**Symptom:** `Error: Cannot resolve parameter $foo`.

**Cause:** the parameter has a class type that cannot be instantiated.

**Fix:** register it in the container or provide a default value.

### Type Mismatch

**Symptom:** `TypeError: Argument $id must be of type int, string given`.

**Cause:** PHP strict types + string route param.

**Fix:** declare the parameter as `string` and cast:

```php
public function show(string $id): Response
{
    $id = (int) $id;
}
```

### Response Mutations Lost

**Symptom:** a middleware sets a header, but the final response doesn't have it.

**Cause:** the controller returns a **new** Response instead of the injected one.

**Fix:**

```php
// ✅ Use the injected response
public function index(Response $response): Response
{
    return $response->html('<h1>Hello</h1>');
}
```

---

## See Also

- [Routes](routes.md) — registering routes with handlers
- [Groups](groups.md) — grouped controllers
- [Middleware](middleware.md) — before/after pipeline
- [Request](request.md) — all request methods
- [Response](response.md) — all response builders
- [Container](container.md) — DI and autowiring

---

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/controllers.md)