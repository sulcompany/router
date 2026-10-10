# Response

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/response.md)

The `Response` object represents the HTTP response. You **build** it in the controller (fluent API) and **return** it — the `Dispatcher` **sends** it.

---

## Table of Contents

- [Overview](#overview)
- [Builders](#builders)
- [Headers & Status](#headers--status)
- [Getters](#getters)
- [Sending](#sending)
- [Full Method Reference](#full-method-reference)
- [Common Patterns](#common-patterns)
- [Common Errors](#common-errors)

---

## Overview

The `Response` is created by the `Dispatcher` at the start of every request and passed to:

- **Middleware** (`before` / `after`)
- **Controllers** (via type-hint `Response $response`)
- **Closures** (`$response` argument)

### Class

```php
namespace SulCompany\Router;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected mixed $body = null;
    protected bool $sent = false;
}
```

**Note:** you almost never instantiate `Response` yourself — the `Dispatcher` does. But you **can** create one if you need a fresh response:

```php
return (new Response())->json(['ok' => true]);
```

### Mutability

Unlike PSR-7, the `Response` is **mutable**. All builder methods return `$this`, enabling chaining.

### Idempotent Send

`send()` uses a `$sent` guard: calling it twice has no effect.

---

## Builders

Builder methods set both the status and the body in one call.

### `html(string $html, int $status = 200): self`

Sets `Content-Type: text/html; charset=utf-8` and the body.

```php
return $response->html('<h1>Hello</h1>');

return $response->html('<h1>Not Found</h1>', 404);
```

### `json(array $data, int $status = 200): self`

Sets `Content-Type: application/json; charset=utf-8` and the JSON-encoded body.

```php
return $response->json(['ok' => true]);

return $response->json(['error' => 'Not Found'], 404);
```

**Encoding options:**

- `JSON_UNESCAPED_UNICODE` — keeps `ç`, `ã`, `é`, etc.
- `JSON_UNESCAPED_SLASHES` — keeps `/` unescaped

So `{"url":"https://mysite.com/path"}` — not `{"url":"https:\/\/mysite.com\/path"}`.

### `text(string $text, int $status = 200): self`

Sets `Content-Type: text/plain; charset=utf-8` and the body.

```php
return $response->text('pong');
```

### `redirect(string $url, int $status = 302): self`

Sets the status and a `Location` header. **No body.**

```php
return $response->redirect('/login');

return $response->redirect('/login', 301);  // permanent
```

### `back(int $status = 302): self`

Redirects to the `HTTP_REFERER` header.

```php
return $response->back();  // back to previous page
```

**Safety:** if the referer's host differs from the current host, redirects to `/` (open redirect protection).

---

## Headers & Status

### `status(int $code): self`

Sets the HTTP status code.

```php
$response->status(201);
```

### `header(string $name, string $value): self`

Sets (or replaces) a header.

```php
$response->header('X-Request-Id', 'abc123');
$response->header('Cache-Control', 'no-store');
```

### Chaining

Everything returns `$this`:

```php
return $response
    ->status(201)
    ->header('X-App', 'SulCompany')
    ->json(['ok' => true]);
```

---

## Getters

### `getStatusCode(): int`

Returns the current status code.

```php
$code = $response->getStatusCode();  // 200
```

### `getBody(): mixed`

Returns the current body (string or `null`).

```php
$body = $response->getBody();  // "<h1>Hello</h1>"
```

### `getHeaders(): array`

Returns all headers.

```php
$headers = $response->getHeaders();
// → ['Content-Type' => '...', 'X-App' => '...']
```

### `hasContent(): bool`

Returns `true` if the body is not `null` **or** there are any headers.

```php
$response->hasContent();  // true if body != null OR headers not empty
```

**Use case:** check if a middleware set a response before overriding it.

---

## Sending

### `send(): void`

Sends the response to the client.

**What it does:**

1. Guard: does nothing if already sent
2. Calls `http_response_code($this->status)`
3. Sends each header via `header()`
4. Echoes the body (if not `null`)
5. Marks `$sent = true`

```php
$response->send();
```

### When to Call It

**You normally don't.** The `Dispatcher` calls `send()` at the end of the request.

**You do** call it in `index.php` when handling errors:

```php
$errorHandler->handle($status, $request, $response)->send();
```

### Output Buffering

`send()` does `echo $body`. If you have output buffering active (`ob_start()`), the body goes to the buffer — you must flush it:

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

📖 [Important notes in README →](../../README.md#-important-notes)

---

## Full Method Reference

| Method | Return | Description |
|--------|--------|-------------|
| `status($code)` | `self` | Sets HTTP status |
| `header($name, $value)` | `self` | Sets a header |
| `html($html, $status)` | `self` | HTML response |
| `json($data, $status)` | `self` | JSON response |
| `text($text, $status)` | `self` | Plain text response |
| `redirect($url, $status)` | `self` | Redirect |
| `back($status)` | `self` | Redirect to referer |
| `getStatusCode()` | `int` | Current status |
| `getBody()` | `mixed` | Current body |
| `getHeaders()` | `array` | All headers |
| `hasContent()` | `bool` | Body or headers present |
| `send()` | `void` | Sends the response |

---

## Common Patterns

### JSON API Response

```php
public function index(Response $response): Response
{
    $users = $this->users->all();
    return $response->json(['data' => $users]);
}
```

### Conditional Response (HTML vs JSON)

```php
public function store(Request $request, Response $response): Response
{
    $user = $this->users->create($request->all());

    if ($request->expectsJson()) {
        return $response->json(['user' => $user], 201);
    }

    return $response->redirect('/users/' . $user->id);
}
```

### 404 with Custom Message

```php
public function show(string $id, Response $response): Response
{
    $user = $this->users->find($id);

    if ($user === null) {
        return $response->json(['error' => 'User not found'], 404);
    }

    return $response->json($user);
}
```

### Redirect After POST

```php
public function store(Request $request, Response $response): Response
{
    $post = $this->posts->create($request->post());
    return $response->redirect('/posts/' . $post->id);
}
```

### Back with Query

```php
return $response->redirect('/users?' . http_build_query(['page' => 2]));
```

### Custom Headers

```php
return $response
    ->header('X-Request-Id', uniqid())
    ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
    ->header('Pragma', 'no-cache')
    ->json(['ok' => true]);
```

### 204 No Content

```php
public function destroy(string $id, Response $response): Response
{
    $this->users->delete($id);
    return $response->status(204);  // no body
}
```

### Download with Content-Disposition

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

---

## Common Errors

### Response Not Sent

**Symptom:** a controller returns a Response, but the page is blank.

**Cause:** the `Dispatcher` didn't call `send()`, or the buffer wasn't flushed.

**Fix (app-level):**

```php
$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
```

The `Dispatcher` calls `send()` automatically.

**Fix (buffer-level):**

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

### `Content-Length: 0` Even With Body

**Symptom:** the response has a body, but the client receives `content-length: 0`.

**Cause:** output buffering is active, and the buffer isn't being flushed.

**Fix:** the same as above — flush all buffers at the end of the script.

### `json()` Returns Malformed JSON

**Symptom:** `$response->json(['key' => $invalidValue])` produces empty output.

**Cause:** `json_encode()` failed silently (e.g., `INF`, `NAN`, invalid UTF-8).

**Fix:** validate data before encoding, or use `JSON_THROW_ON_ERROR`:

```php
try {
    return $response->json(['data' => $data]);
} catch (\JsonException $e) {
    return $response->json(['error' => 'Invalid data'], 500);
}
```

**Note:** the base `Response` doesn't throw — it silently sets the body to `false`. If you need to detect, check `getBody()`.

### Redirect Doesn't Work

**Symptom:** calling `redirect()` but the browser stays on the same page.

**Causes:**

1. **Headers already sent** — something echoed before `send()`. Check `ob_start()`.
2. **Wrong status** — check `getStatusCode()`.
3. **The Location header is missing** — check `getHeaders()`.

**Fix:**

```php
return $response->redirect('/login');  // 302 by default
```

### Middleware Header Lost

**Symptom:** a middleware adds a header, but the final response doesn't have it.

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

- [Request](request.md) — accessing the HTTP request
- [Controllers](controllers.md) — returning responses from controllers
- [Middleware](middleware.md) — modifying responses in middleware
- [Error Handling](error-handling.md) — building error responses

---

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/response.md)