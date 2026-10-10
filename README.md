# SulCompany Router

> A lightweight, dependency-free PHP router with dynamic routes, middleware, route groups, contexts, and built-in error handling.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PSR-11](https://img.shields.io/badge/PSR--11-compliant-brightgreen)](https://www.php-fig.org/psr/psr-11/)
[![Latest Version](https://img.shields.io/packagist/v/sulcompany/router)](https://packagist.org/packages/sulcompany/router)
[![Total Downloads](https://img.shields.io/packagist/dt/sulcompany/router)](https://packagist.org/packages/sulcompany/router)

🇺🇸 **English** | [🇵🇹 Português](README.pt.md)

---

## 📖 Overview

**SulCompany Router** is a mini routing framework for PHP 8.1+. It is **not** PSR-7, **not** FastRoute. It is a **simple, direct, opinionated** alternative for developers who want:

- HTTP routing
- Middleware pipeline
- Route groups with prefix, name, and context
- Configurable error handling
- An integrated PSR-11 container (optional)
- **Zero dependencies besides `psr/container`**

It was built to be **predictable**, **readable**, and **extensible** — without the weight of a full framework.

---

## ✨ Features

- ✅ REST routes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
- ✅ Dynamic parameters (`/user/{id}`)
- ✅ Wildcard parameters (`/files/{path*}`)
- ✅ Named routes and URL generation
- ✅ Nested groups (prefix + name + context + middleware)
- ✅ Contexts (`admin`, `ecommerce`, ...) for differentiated error views
- ✅ `before` / `after` middleware pipeline
- ✅ PSR-11 container with autowiring
- ✅ Route cache (via `var_export`)
- ✅ Error handling via `ErrorHandlerInterface`
- ✅ Automatic JSON / AJAX detection
- ✅ No heavy dependencies

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| **PHP** | `^8.1` |
| **Composer** | `^2.0` |
| **psr/container** | `^2.0` (installed automatically) |

---

## 🚀 Installation

```bash
composer require sulcompany/router
```

---

## ⚡ Quick Start

### 1. Create `public/index.php`

```php
<?php

declare(strict_types=1);

ob_start();

use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://mysite.com');

$router->get('/', function ($request, $response) {
    return $response->html('<h1>Hello, world!</h1>');
}, name: 'home');

$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();

while (ob_get_level() > 0) {
    ob_end_flush();
}
```

### 2. Test it

- `GET /` → returns HTML
- `GET /user/42` → executes `UserController::show('42')`

**Done.** 🎉

---

## 🧠 The Basics

### Routes

```php
$router->get('/about', [SiteController::class, 'about'], name: 'site.about');
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');
```

📖 [Full route documentation →](docs/en/routes.md)

### Groups

```php
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

📖 [Full group documentation →](docs/en/groups.md)

### Middleware

```php
class AuthMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        if (!auth()->check()) {
            $response->redirect('/login');
            return false;
        }
        return true;
    }
}
```

📖 [Full middleware documentation →](docs/en/middleware.md)

### Controllers

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
```

📖 [Full controller documentation →](docs/en/controllers.md)

### Request

```php
$request->method();
$request->path();
$request->header('accept');
$request->get('page');
$request->post('name');
$request->json('field');
$request->expectsJson();
```

📖 [Full request documentation →](docs/en/request.md)

### Response

```php
return $response->html('<h1>Hello</h1>');
return $response->json(['ok' => true]);
return $response->redirect('/login');
```

📖 [Full response documentation →](docs/en/response.md)

### Error Handling

```php
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        if ($request->expectsJson()) {
            return $response->json(['error' => true], $status);
        }
        return $response->html("<h1>Error {$status}</h1>", $status);
    }
}
```

📖 [Full error handling documentation →](docs/en/error-handling.md)

### Container (DI)

```php
$container = new Container();
$container->singleton(PDO::class, fn() => new PDO('mysql:...'));
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
```

📖 [Full container documentation →](docs/en/container.md)

### Route Cache

```php
$router->enableCache(__DIR__ . '/cache/routes.php');
if (!$router->loadCache()) {
    $router->get('/', ...);
    $router->compile();
    $router->cacheCompiledRoutes();
}
```

📖 [Full cache documentation →](docs/en/cache.md)

### URL Generation

```php
$router->urlFor('user.show', ['id' => 42]);
// → https://mysite.com/user/42
```

📖 [Full URL generation documentation →](docs/en/url-generation.md)

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [Routes](docs/en/routes.md) | HTTP methods, dynamic params, wildcards |
| [Groups](docs/en/groups.md) | Prefix, name, context, middleware, nesting |
| [Middleware](docs/en/middleware.md) | Before/after, types, order |
| [Controllers](docs/en/controllers.md) | Closures, classes, DI |
| [Request](docs/en/request.md) | All request methods |
| [Response](docs/en/response.md) | All response builders |
| [Error Handling](docs/en/error-handling.md) | Contract, default, custom |
| [Container](docs/en/container.md) | PSR-11, bind, singleton, autowiring |
| [Cache](docs/en/cache.md) | Route compilation and caching |
| [URL Generation](docs/en/url-generation.md) | Named routes and urlFor() |

📖 [🇵🇹 Documentação em Português →](docs/pt/)

---

## ⚠️ Important Notes

### Output Buffering

```php
ob_start();           // at the top

// ... your code ...

while (ob_get_level() > 0) {
    ob_end_flush();   // at the end
}
```

Without this, your pages may render **blank** with `content-length: 0`.

### Exceptions

The Router does **not** catch exceptions. Use `try/catch` in your `index.php`.

### Not PSR-7

`Request` and `Response` are mutable and minimalist.

### PSR-11 Container

The `Container` class implements `Psr\Container\ContainerInterface`.

---

## 🧩 Compatibility

| PHP | Supported |
|-----|-----------|
| 8.1 | ✅ |
| 8.2 | ✅ |
| 8.3 | ✅ |
| 8.4 | ✅ (not yet tested) |
| 7.x | ❌ |

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Open a Pull Request

---

## 🐛 Reporting Issues

[Open an issue](https://github.com/sulcompany/router/issues) with PHP version, Router version, and a minimal reproduction.

---

## 👥 Credits

- **Francisco Dulo** — author
- **Sul Company Lda** — maintainer
- **Contributors** — everyone who helped

---

## 📄 License

MIT — see [LICENSE](LICENSE).

---

**SulCompany Router** — made with 🖤 in Angola.