# Request

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/request.md)

The `Request` object represents the current HTTP request. It provides access to the method, path, headers, query string, POST body, JSON body, uploaded files, client IP, and content negotiation helpers.

---

## Table of Contents

- [Overview](#overview)
- [Route / Path / Method](#route--path--method)
- [Headers](#headers)
- [Input Data](#input-data)
- [Files](#files)
- [Environment](#environment)
- [Content Negotiation](#content-negotiation)
- [Full Method Reference](#full-method-reference)
- [Common Patterns](#common-patterns)
- [Common Errors](#common-errors)

---

## Overview

The `Request` is created by the `Dispatcher` at the start of every request and passed to:

- **Middleware** (`before` / `after`)
- **Controllers** (via type-hint `Request $request`)
- **Closures** (`$request` argument)

### Class

```php
namespace SulCompany\Router;

class Request
{
    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $json = [],
        array $files = [],
        string $method = 'GET',
        string $path = '/'
    );
}
```

**Note:** you almost never instantiate `Request` yourself — the `Dispatcher` does.

### Mutability

Unlike PSR-7, the `Request` is **mutable**. Two setters are called internally by the `Dispatcher`:

- `setRoute(Route $route)` — after matching
- `setParams(array $params)` — after matching (if implemented)

---

## Route / Path / Method

### `route(): ?Route`

Returns the **matched `Route` object**, or `null` if the request hasn't been dispatched yet.

```php
$route = $request->route();

if ($route !== null) {
    echo $route->getName();                       // "user.show"
    echo $route->getAttribute('context', 'ecommerce');  // "admin" or "ecommerce"
}
```

### `method(): string`

Returns the HTTP method, **uppercased**.

```php
$request->method();  // "GET", "POST", "PUT", "PATCH", "DELETE"
```

### `isMethod(string $method): bool`

Case-insensitive check.

```php
if ($request->isMethod('post')) {
    // ...
}
```

### `path(): string`

Returns the request path, **without** query string.

```php
// URL: https://mysite.com/user/42?ref=email
$request->path();  // "/user/42"
```

---

## Headers

### `header(?string $name = null): array|string|null`

Returns **all headers** or **one header** (case-insensitive).

```php
// All headers
$headers = $request->header();
// → ['accept' => '...', 'user-agent' => '...', ...]

// One header
$accept = $request->header('accept');
// → "text/html,..."

// Case-insensitive
$request->header('Accept');       // same as header('accept')
$request->header('ACCEPT');       // same
```

**Returns `null`** if the header doesn't exist.

### `userAgent(): ?string`

Shorthand for `header('user-agent')`.

```php
$ua = $request->userAgent();
// → "Mozilla/5.0 ..."
```

### Header Source

Headers are extracted from `$_SERVER` (keys starting with `HTTP_`), lowercased, and dashes-separated:

```
HTTP_USER_AGENT      → user-agent
HTTP_ACCEPT          → accept
HTTP_X_REQUESTED_WITH → x-requested-with
```

**Note:** `Content-Type` and `Content-Length` are **not** in `$_SERVER['HTTP_*']` — they come from `CONTENT_TYPE` and `CONTENT_LENGTH`. The Router **does not** expose those as headers. Use `$request->header('content-type')` only if your server sets `HTTP_CONTENT_TYPE`.

---

## Input Data

### GET (query string)

```php
$request->get(): array;
$request->get('page'): mixed;
$request->get('page', 1): mixed;  // default
```

**Example:**

```php
// URL: /search?q=router&page=2
$request->get('q');       // "router"
$request->get('page');    // "2"
$request->get('limit', 10); // 10 (default)
$request->get();          // ['q' => 'router', 'page' => '2']
```

### POST (form body)

```php
$request->post(): array;
$request->post('name'): mixed;
$request->post('name', 'Anonymous'): mixed;
```

**Example:**

```php
// Form: name=Alice&email=alice@example.com
$request->post('name');   // "Alice"
$request->post();         // ['name' => 'Alice', 'email' => '...']
```

### JSON (request body)

```php
$request->json(): array;
$request->json('field'): mixed;
$request->json('field', 'default'): mixed;
```

**Example:**

```php
// Content-Type: application/json
// Body: {"name": "Alice", "tags": ["a","b"]}
$request->json('name');    // "Alice"
$request->json('tags');    // ['a', 'b']
$request->json();          // ['name' => 'Alice', 'tags' => [...]]
```

**Note:** JSON is only parsed when:

- Method is `POST`, `PUT`, `PATCH`, or `DELETE`
- `Content-Type` contains `application/json`

### All Combined

```php
$request->all(): array;
$request->all('key'): mixed;
```

**Merge order:** `json` overrides `post` overrides `get`.

| Key in | Key in | Key in | `all()` returns |
|--------|--------|--------|-----------------|
| GET | POST | JSON | JSON value |
| GET | POST | — | POST value |
| GET | — | — | GET value |

**Note:** `all()` does **not** include files.

### Raw Body

The Router does **not** expose the raw request body. If you need it:

```php
$raw = file_get_contents('php://input');
```

---

## Files

### `file(?string $key = null, $default = null)`

Returns **all uploaded files** or **one file**.

```php
// All
$request->file();
// → ['avatar' => ['name' => '...', 'tmp_name' => '...', ...]]

// One
$request->file('avatar');
// → ['name' => 'photo.jpg', 'tmp_name' => '/tmp/phpXXXX', 'size' => 12345, ...]
```

**Returns the raw `$_FILES` entry.** No processing — the array shape follows PHP's native format.

### Array of Files

For `name="files[]"` (multiple), `$_FILES` is restructured by PHP. The Router returns it **as-is**.

---

## Environment

### `ip(): ?string`

Returns the client IP, respecting `X-Forwarded-For`:

```php
$request->ip();  // "203.0.113.42"
```

**Logic:**

1. If `HTTP_X_FORWARDED_FOR` is set → first IP in the list
2. Otherwise → `REMOTE_ADDR`
3. Returns `null` if neither is available

> ⚠️ **Security note:** `X-Forwarded-For` is **client-controlled** unless you're behind a trusted proxy. Do **not** use it for security decisions unless you've verified the proxy.

### `userAgent(): ?string`

Covered in [Headers](#headers).

---

## Content Negotiation

Helpers to detect what the client expects.

### `wantsJson(): bool`

Returns `true` if the `Accept` header contains `application/json`.

```php
// Accept: application/json
$request->wantsJson();  // true
```

### `isAjax(): bool`

Returns `true` if `X-Requested-With: XMLHttpRequest`.

```php
// X-Requested-With: XMLHttpRequest
$request->isAjax();  // true
```

### `expectsJson(): bool`

Combines both. **Use this one.**

```php
// Either of the above
$request->expectsJson();  // true
```

### Comparison

| Header | `wantsJson` | `isAjax` | `expectsJson` |
|--------|-------------|----------|---------------|
| `Accept: application/json` | ✅ | ❌ | ✅ |
| `X-Requested-With: XMLHttpRequest` | ❌ | ✅ | ✅ |
| Both | ✅ | ✅ | ✅ |
| Neither | ❌ | ❌ | ❌ |

### Example

```php
public function store(Request $request, Response $response): Response
{
    if ($request->expectsJson()) {
        return $response->json(['ok' => true]);
    }

    return $response->redirect('/success');
}
```

---

## Full Method Reference

| Method | Return | Description |
|--------|--------|-------------|
| `route()` | `?Route` | Matched route |
| `method()` | `string` | HTTP method (uppercased) |
| `isMethod($method)` | `bool` | Case-insensitive check |
| `path()` | `string` | Request path |
| `header($name = null)` | `array\|string\|null` | All or one header |
| `userAgent()` | `?string` | User-Agent shorthand |
| `get($key = null, $default = null)` | `mixed` | Query string |
| `post($key = null, $default = null)` | `mixed` | POST body |
| `json($key = null, $default = null)` | `mixed` | JSON body |
| `file($key = null, $default = null)` | `mixed` | Uploaded files |
| `all($key = null, $default = null)` | `mixed` | Merged data (GET + POST + JSON) |
| `ip()` | `?string` | Client IP |
| `wantsJson()` | `bool` | `Accept: application/json` |
| `isAjax()` | `bool` | `X-Requested-With: XMLHttpRequest` |
| `expectsJson()` | `bool` | `wantsJson() \|\| isAjax()` |

---

## Common Patterns

### Conditional Response

```php
public function store(Request $request, Response $response): Response
{
    // ... do work ...

    if ($request->expectsJson()) {
        return $response->json(['ok' => true], 201);
    }

    return $response->redirect('/users');
}
```

### Get a Value with Fallback

```php
$page = (int) $request->get('page', 1);
$limit = (int) $request->get('limit', 20);
```

### Detect Method in One Line

```php
if ($request->isMethod('POST')) {
    // handle form
}
```

### Unified Data Access

```php
// Works for query string, form, AND JSON
$name = $request->all('name');
```

### Accessing the Route

```php
public function show(Request $request, Response $response): Response
{
    $context = $request->route()?->getAttribute('context') ?? 'ecommerce';
    return $response->json(['context' => $context]);
}
```

### Client IP Behind Proxy

```php
$ip = $request->ip();
// If you trust your proxy, this is safe.
// Otherwise, use REMOTE_ADDR directly.
```

---

## Common Errors

### `header()` Returns `null`

**Symptom:** `$request->header('content-type')` returns `null`.

**Cause:** `Content-Type` is not in `$_SERVER['HTTP_*']`. It's in `$_SERVER['CONTENT_TYPE']`.

**Fix:** read it directly:

```php
$contentType = $_SERVER['CONTENT_TYPE'] ?? null;
```

### JSON Body Is Empty

**Symptom:** `$request->json('field')` returns `null` for a JSON request.

**Causes:**

1. **Content-Type is not `application/json`** — the Router only parses JSON when this header is present
2. **Method is not POST/PUT/PATCH/DELETE** — JSON is only parsed for those
3. **Malformed JSON** — invalid syntax

**Fix:**

```bash
# Test with curl:
curl -X POST https://mysite.com/api \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice"}'
```

### `all()` Missing a Value

**Symptom:** a POST value is not in `all()`.

**Cause:** a JSON value with the same key **overrode** it.

**Fix:** use `post()` directly if you need the POST value:

```php
$name = $request->post('name');  // specific
```

### `ip()` Returns `null`

**Symptom:** `$request->ip()` is `null`.

**Cause:** running in CLI or a server without `REMOTE_ADDR`.

**Fix:** fallback in your code:

```php
$ip = $request->ip() ?? '0.0.0.0';
```

### Wrong Method Detected

**Symptom:** `$request->method()` is `GET` but you sent `POST`.

**Cause:** the server might rewrite. Check with `var_dump($_SERVER['REQUEST_METHOD'])`.

**Fix:** for method spoofing, use `_method` in the POST body:

```html
<form method="POST">
    <input type="hidden" name="_method" value="PUT">
    ...
</form>
```

The Router honors `_method` (POST only) and converts it to `PUT`.

---

## See Also

- [Response](response.md) — building responses
- [Controllers](controllers.md) — using `Request` in controllers
- [Middleware](middleware.md) — using `Request` in middleware
- [Routes](routes.md) — accessing route params

---

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/request.md)