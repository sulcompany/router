# URL Generation

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/url-generation.md)

Generate URLs from **named routes** using `urlFor()`. This decouples your application from hardcoded paths — change a route in one place, and every link updates.

---

## Table of Contents

- [Overview](#overview)
- [Signature](#signature)
- [Basic Usage](#basic-usage)
- [Route Parameters](#route-parameters)
- [Wildcards](#wildcards)
- [Query Strings](#query-strings)
- [Named Routes in Groups](#named-routes-in-groups)
- [Missing Parameters](#missing-parameters)
- [Errors](#errors)
- [Common Patterns](#common-patterns)
- [Common Errors](#common-errors)

---

## Overview

`urlFor()` builds a URL from a named route and an array of parameters.

### Why Use It

**Without `urlFor()`:**

```php
<a href="/user/<?= $user->id ?>">Profile</a>
```

**Problems:**

- Hardcoded path
- If `/user/{id}` becomes `/profile/{id}`, you must update every link
- No URL encoding
- No query-string handling

**With `urlFor()`:**

```php
<a href="<?= $router->urlFor('user.show', ['id' => $user->id]) ?>">Profile</a>
```

**Benefits:**

- Single source of truth
- Auto URL encoding
- Query strings handled
- IDE-navigable route names

---

## Signature

```php
public function urlFor(
    string $name,
    array $params = [],
    array $query = []
): string;
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$name` | `string` | ✅ | Route name |
| `$params` | `array` | ❌ | Path parameters |
| `$query` | `array` | ❌ | Query string parameters |

**Returns:** the full URL, prefixed with the `projectUrl`.

---

## Basic Usage

```php
$router->get('/about', [SiteController::class, 'about'], name: 'about');

$url = $router->urlFor('about');
// → https://mysite.com/about
```

**Note:** the `projectUrl` (passed to `new Router(...)`) is prepended.

```php
$router = new Router('https://mysite.com');
$router->urlFor('about');  // https://mysite.com/about
```

If you passed `'https://mysite.com/app'`:

```php
$router = new Router('https://mysite.com/app');
$router->urlFor('about');  // https://mysite.com/app/about
```

---

## Route Parameters

Pass parameters as an associative array:

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$router->urlFor('user.show', ['id' => 42]);
// → https://mysite.com/user/42
```

### Multiple Parameters

```php
$router->get('/post/{slug}/comment/{id}', [CommentController::class, 'show'], name: 'comment.show');

$router->urlFor('comment.show', [
    'slug' => 'hello-world',
    'id'   => 7,
]);
// → https://mysite.com/post/hello-world/comment/7
```

### Parameter Order Doesn't Matter

```php
$router->urlFor('comment.show', ['id' => 7, 'slug' => 'hello-world']);
// → https://mysite.com/post/hello-world/comment/7
```

The Router replaces placeholders by **name**, not by position.

### URL Encoding

Values are **not** automatically URL-encoded by `urlFor()`. Encode manually if needed:

```php
$router->urlFor('post.show', ['slug' => urlencode('hello world')]);
// → https://mysite.com/post/hello+world
```

---

## Wildcards

Wildcard parameters (`{path*}`) accept an **array**:

```php
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');

$router->urlFor('files.download', ['path' => ['docs', '2024', 'file.pdf']]);
// → https://mysite.com/files/docs/2024/file.pdf
```

### String Value

You can pass a string — it's used as-is:

```php
$router->urlFor('files.download', ['path' => 'docs/2024/file.pdf']);
// → https://mysite.com/files/docs/2024/file.pdf
```

### Empty Wildcard

An empty array produces no segments:

```php
$router->urlFor('files.download', ['path' => []]);
// → https://mysite.com/files/
```

---

## Query Strings

Pass a **third argument** for query parameters:

```php
$router->urlFor('user.show', ['id' => 42], ['ref' => 'email']);
// → https://mysite.com/user/42?ref=email
```

### Multiple Query Parameters

```php
$router->urlFor('users.index', [], ['page' => 2, 'sort' => 'name']);
// → https://mysite.com/users?page=2&sort=name
```

### Array Query Parameters

```php
$router->urlFor('users.index', [], ['tags' => ['php', 'router']]);
// → https://mysite.com/users?tags%5B0%5D=php&tags%5B1%5D=router
```

Uses PHP's `http_build_query()`, so arrays are encoded in PHP format.

### Only Query, No Path

```php
$router->get('/', [HomeController::class, 'index'], name: 'home');

$router->urlFor('home', [], ['utm_source' => 'newsletter']);
// → https://mysite.com/?utm_source=newsletter
```

---

## Named Routes in Groups

When a route is inside a group with `name`, the prefix is applied:

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});

$router->urlFor('admin.users.index');
// → https://mysite.com/admin/users
```

### Nested Prefixes

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
    });
});

$router->urlFor('admin.users.list');
// → https://mysite.com/admin/users/list
```

### Named Routes with Parameters

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users/{id}', [UserController::class, 'show'], name: 'users.show');
});

$router->urlFor('admin.users.show', ['id' => 42]);
// → https://mysite.com/admin/users/42
```

---

## Missing Parameters

If a route has parameters and you **don't** pass them, the placeholder is **removed**:

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$router->urlFor('user.show');
// → https://mysite.com/user/
```

**Behavior:** the placeholder `{id}` is stripped from the URL.

**Note:** this is generally **not what you want** — always pass required parameters.

### What Gets Removed

The Router uses this regex to strip unreplaced placeholders:

```
\{[a-zA-Z_][a-zA-Z0-9_-]*\*?\}
```

So both `{id}` and `{path*}` are removed if not replaced.

---

## Errors

### Unknown Route Name

```php
$router->urlFor('nonexistent');
// ❌ InvalidArgumentException: Named route 'nonexistent' not found.
```

**Cause:** the name doesn't exist in the route registry.

**Common reasons:**

1. **Typo** — `users.show` vs `user.show`
2. **Route not registered** — the route is behind a conditional
3. **Cache not regenerated** — after adding a route, clear the cache

### Route Not in `nameIndex`

If a route has **no name** (you didn't pass `name:`), it's not in the index:

```php
$router->get('/about', [SiteController::class, 'about']);  // no name

$router->urlFor('about');  // ❌ not found
```

**Fix:** always give routes a name when you plan to generate URLs.

---

## Common Patterns

### RESTful Resource Links

```php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');
$router->get('/posts/{id}', [PostController::class, 'show'], name: 'posts.show');
$router->get('/posts/{id}/edit', [PostController::class, 'edit'], name: 'posts.edit');

// In a view:
<a href="<?= $router->urlFor('posts.index') ?>">All posts</a>
<a href="<?= $router->urlFor('posts.show', ['id' => $post->id]) ?>"><?= $post->title ?></a>
<a href="<?= $router->urlFor('posts.edit', ['id' => $post->id]) ?>">Edit</a>
```

### Pagination

```php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');

// Page 3
$url = $router->urlFor('posts.index', [], ['page' => 3]);
// → https://mysite.com/posts?page=3
```

### Redirect After Action

```php
public function store(Request $request, Response $response): Response
{
    $post = $this->posts->create($request->all());

    return $response->redirect(
        $this->router->urlFor('posts.show', ['id' => $post->id])
    );
}
```

### Admin Panel Links

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});

// In an admin view:
<a href="<?= $router->urlFor('admin.dashboard') ?>">Dashboard</a>
<a href="<?= $router->urlFor('admin.users.index') ?>">Users</a>
```

### Email Links

```php
$verifyUrl = $router->urlFor('auth.verify', ['token' => $token]);
// → https://mysite.com/auth/verify/abc123

mail($email, 'Verify your account', "Click: {$verifyUrl}");
```

### Absolute vs Relative

`urlFor()` always returns an **absolute URL** (with scheme and host):

```php
$router->urlFor('about');
// → https://mysite.com/about
```

If you need a **relative** URL:

```php
$url = parse_url($router->urlFor('about'), PHP_URL_PATH);
// → /about
```

### Injecting the Router into Views

Because `urlFor()` is on the Router, pass it to your view engine:

```php
$this->view->render('page', [
    'router' => $router,
    'post'   => $post,
]);
```

```php
<a href="<?= $router->urlFor('posts.show', ['id' => $post->id]) ?>">
```

### Route Helper (in a Template Engine)

If you use Plates, register a function:

```php
$engine->registerFunction('route', fn(string $name, array $params = [], array $query = []) =>
    $router->urlFor($name, $params, $query)
);
```

Then in templates:

```html
<a href="<?= $this->route('posts.show', ['id' => $post->id]) ?>">View</a>
```

---

## Common Errors

### `InvalidArgumentException` on `urlFor()`

**Symptom:** `Named route 'x' not found.`

**Causes:**

1. **Typo** in the route name
2. **Route not registered** (behind a conditional you didn't enter)
3. **Cache not regenerated** after adding the route

**Fix:**

```php
// Debug: list all registered names
var_dump(array_keys($router->getNameIndex()));
```

### URL Missing Query String

**Symptom:** `urlFor('posts.index', [], ['page' => 2])` doesn't add `?page=2`.

**Cause:** passing params in the wrong argument.

**Fix:** the **third** argument is for query string:

```php
// ✅ Correct
$router->urlFor('posts.index', [], ['page' => 2]);

// ❌ Wrong — page becomes a path parameter
$router->urlFor('posts.index', ['page' => 2]);
```

### Wrong Host or Scheme

**Symptom:** `urlFor()` returns a URL with the wrong host.

**Cause:** `projectUrl` was set incorrectly when creating the Router.

**Fix:**

```php
$router = new Router('https://mysite.com');  // correct
$router = new Router('https://staging.mysite.com');  // staging
```

**Tip:** load the URL from env:

```php
$router = new Router($_ENV['APP_URL']);
```

### Path Placeholder Left in URL

**Symptom:** `https://mysite.com/user/{id}` (literal `{id}`).

**Cause:** the placeholder has a character not allowed by the regex.

**Supported placeholder pattern:** `[a-zA-Z_][a-zA-Z0-9_-]*`

**Fix:** rename the placeholder to use only letters, numbers, `_`, `-`.

### Wildcard Array Not Expanding

**Symptom:** `urlFor('files', ['path' => ['a', 'b']])` produces `Array`.

**Cause:** passing the array as a string context.

**Fix:** `urlFor()` handles arrays for wildcards automatically. Check the route:

```php
$router->get('/files/{path*}', ..., name: 'files');
// ✅ Wildcard
```

vs

```php
$router->get('/files/{path}', ..., name: 'files');
// ❌ Single segment — passing array becomes "Array"
```

### Cache Not Regenerated

**Symptom:** a newly named route isn't found.

**Cause:** the name index is cached. After adding routes, clear the cache.

**Fix:**

```bash
rm storage/cache/routes.php
```

📖 [Cache docs →](cache.md)

---

## See Also

- [Routes](routes.md) — naming routes
- [Groups](groups.md) — name prefixes
- [Cache](cache.md) — the name index and cache
- [Response](response.md) — `redirect()` with `urlFor()`

---

[← Back to README](../../README.md) | [🇵🇹 Português](../pt/url-generation.md)