SulCompany Router
A lightweight, dependency-free PHP router with dynamic routes, middleware, route groups, contexts, and built-in error handling.

https://img.shields.io/badge/php-%5E8.1-blue
https://img.shields.io/badge/license-MIT-green
https://img.shields.io/badge/PSR--11-compliant-brightgreen
https://img.shields.io/packagist/v/sulcompany/router
https://img.shields.io/packagist/dt/sulcompany/router

🇺🇸 English | 🇵🇹 Português

📖 Overview
SulCompany Router is a mini routing framework for PHP 8.1+. It is not PSR-7, not FastRoute. It is a simple, direct, opinionated alternative for developers who want:

HTTP routing

Middleware pipeline

Route groups with prefix, name, and context

Configurable error handling

An integrated PSR-11 container (optional)

Zero dependencies besides psr/container

It was built to be predictable, readable, and extensible — without the weight of a full framework.

✨ Features
✅ REST routes (GET, POST, PUT, PATCH, DELETE)

✅ Dynamic parameters (/user/{id})

✅ Wildcard parameters (/files/{path*})

✅ Named routes and URL generation

✅ Nested groups (prefix + name + context + middleware)

✅ Contexts (admin, ecommerce, ...) for differentiated error views

✅ before / after middleware pipeline

✅ PSR-11 container with autowiring

✅ Route cache (via var_export)

✅ Error handling via ErrorHandlerInterface

✅ Automatic JSON / AJAX detection

✅ No heavy dependencies

📋 Requirements
Requirement	Version
PHP	^8.1
Composer	^2.0
psr/container	^2.0 (installed automatically)
🚀 Installation
bash
composer require sulcompany/router
⚡ Quick Start
1. Create public/index.php
php
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
2. Test it
GET / → returns HTML

GET /user/42 → executes UserController::show('42')

Done. 🎉

🧠 The Basics
Routes
php
$router->get('/about', [SiteController::class, 'about'], name: 'site.about');
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');
📖 Full route documentation →

Groups
php
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
📖 Full group documentation →

Middleware
php
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
📖 Full middleware documentation →

Controllers
php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
📖 Full controller documentation →

Request
php
$request->method();
$request->path();
$request->header('accept');
$request->get('page');
$request->post('name');
$request->json('field');
$request->expectsJson();
📖 Full request documentation →

Response
php
return $response->html('<h1>Hello</h1>');
return $response->json(['ok' => true]);
return $response->redirect('/login');
📖 Full response documentation →

Error Handling
php
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
📖 Full error handling documentation →

Container (DI)
php
$container = new Container();
$container->singleton(PDO::class, fn() => new PDO('mysql:...'));
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
📖 Full container documentation →

Route Cache
php
$router->enableCache(__DIR__ . '/cache/routes.php');
if (!$router->loadCache()) {
    $router->get('/', ...);
    $router->compile();
    $router->cacheCompiledRoutes();
}
📖 Full cache documentation →

URL Generation
php
$router->urlFor('user.show', ['id' => 42]);
// → https://mysite.com/user/42
📖 Full URL generation documentation →

📚 Documentation
Document	Description
Routes	HTTP methods, dynamic params, wildcards
Groups	Prefix, name, context, middleware, nesting
Middleware	Before/after, types, order
Controllers	Closures, classes, DI
Request	All request methods
Response	All response builders
Error Handling	Contract, default, custom
Container	PSR-11, bind, singleton, autowiring
Cache	Route compilation and caching
URL Generation	Named routes and urlFor()
📖 🇵🇹 Documentação em Português →

⚠️ Important Notes
Output Buffering
php
ob_start();           // at the top

// ... your code ...

while (ob_get_level() > 0) {
    ob_end_flush();   // at the end
}
Without this, your pages may render blank with content-length: 0.

Exceptions
The Router does not catch exceptions. Use try/catch in your index.php.

Not PSR-7
Request and Response are mutable and minimalist.

PSR-11 Container
The Container class implements Psr\Container\ContainerInterface.

🧩 Compatibility
PHP	Supported
8.1	✅
8.2	✅
8.3	✅
8.4	✅ (not yet tested)
7.x	❌
🤝 Contributing
Fork the repository

Create a feature branch

Commit your changes

Push to the branch

Open a Pull Request

🐛 Reporting Issues
Open an issue with PHP version, Router version, and a minimal reproduction.

👥 Credits
Francisco Dulo — author

Sul Company Lda — maintainer

Contributors — everyone who helped

📄 License
MIT — see LICENSE.

SulCompany Router — made with 🖤 in Angola.