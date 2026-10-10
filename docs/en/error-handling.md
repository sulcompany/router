Error Handling
← Back to README | 🇵🇹 Português

The Router handles routing errors (404, 405, 400) and lets the application handle application errors (500, 503) via a simple contract: ErrorHandlerInterface.

Table of Contents
Overview

The Contract

DefaultErrorHandler

Custom Handler

Using the Handler

How the Dispatcher Handles Errors

Status Code Reference

Exceptions

Common Patterns

Common Errors

Overview
The Router divides error handling into two layers:

Layer	Responsibility	Examples
Router	Detecting routing errors, providing a default handler	404, 405, 400
Application	Deciding what the user sees, logging, custom views	500, 503, custom 404
Key principle: the Router does not know what a "view" is. It only knows:

The status code

Whether the client wants JSON

The matched route (with context attribute)

The application implements ErrorHandlerInterface to decide how to render the error.

The Contract
php
namespace SulCompany\Router\Contracts;

use SulCompany\Router\Request;
use SulCompany\Router\Response;

interface ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response;
}
Important Rules
The handler does NOT send the response — it only returns it

The caller sends it — either the Dispatcher or the index.php

The handler has access to — the status, the Request, and a fresh Response

Why Return, Not Send?
Because sending is the last step. The caller may want to:

Add middleware-like headers

Log the response

Send it in a specific order

Returning keeps the handler pure and testable.

DefaultErrorHandler
The Router ships a fallback for when the app doesn't register one.

php
namespace SulCompany\Router;

class DefaultErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        $message = $this->message($status);

        if ($request->expectsJson()) {
            return $response->json([
                'success' => false,
                'code'    => $status,
                'message' => $message,
            ], $status);
        }

        return $response->text("{$status} — {$message}", $status);
    }

    protected function message(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            default => 'Error',
        };
    }
}
Behavior
Client	Response
JSON / AJAX	{"success":false,"code":404,"message":"Not Found"}
Browser	404 — Not Found (text/plain)
Example: JSON
json
{
    "success": false,
    "code": 404,
    "message": "Not Found"
}
Example: HTML
text
404 — Not Found
Note: the default handler returns text/plain, not HTML. Applications that want HTML pages must implement their own handler.

When It's Used
The DefaultErrorHandler is used when:

No handler is registered in the container

The index.php falls back to new DefaultErrorHandler()

The Dispatcher does not call it automatically — the caller does.

Custom Handler
Implement ErrorHandlerInterface in your application.

Minimal Example
php
namespace App\Http;

use SulCompany\Router\Contracts\ErrorHandlerInterface;
use SulCompany\Router\Request;
use SulCompany\Router\Response;

final class AppErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        if ($request->expectsJson()) {
            return $response->json([
                'success' => false,
                'code'    => $status,
                'message' => "Error {$status}",
            ], $status);
        }

        return $response->html("<h1>Error {$status}</h1>", $status);
    }
}
With Views (Plates, Twig, Blade...)
php
namespace App\Http;

use SulCompany\Router\Contracts\ErrorHandlerInterface;
use SulCompany\Router\Request;
use SulCompany\Router\Response;
use App\View\View;

final class AppErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private View $view) {}

    public function handle(int $status, Request $request, Response $response): Response
    {
        // JSON / AJAX
        if ($request->expectsJson()) {
            return $response->json([
                'success' => false,
                'code'    => $status,
                'message' => $this->message($status),
            ], $status);
        }

        // HTML — pick view based on context
        $context = $request->route()?->getAttribute('context') ?? 'ecommerce';
        $view = "{$context}/errors/{$status}";

        return $response->html(
            $this->view->render($view, ['status' => $status]),
            $status
        );
    }

    private function message(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida',
            401 => 'Não autenticado',
            403 => 'Acesso negado',
            404 => 'Página não encontrada',
            405 => 'Método não permitido',
            419 => 'Sessão expirada',
            500 => 'Erro interno do servidor',
            503 => 'Serviço temporariamente indisponível',
            default => 'Ocorreu um erro',
        };
    }
}
Using context
The context attribute is set by group(context: 'admin') and inherited.

php
$router->group('/admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index']);
});

// Error on /admin/* routes → context = 'admin' → view "admin/errors/404"
Route	Context	View
/users (no group)	ecommerce (default)	ecommerce/errors/404
/admin/users	admin	admin/errors/404
Using the Handler
Register in the Container
php
// bootstrap/app.php or a provider
$container->singleton(ErrorHandlerInterface::class, function ($c) {
    return new AppErrorHandler($c->get(View::class));
});
Call from index.php
php
$errorHandler = $container->has(ErrorHandlerInterface::class)
    ? $container->get(ErrorHandlerInterface::class)
    : new DefaultErrorHandler();

$dispatcher = new Dispatcher($router, $container);

try {
    $ok = $dispatcher->dispatch();

    if (!$ok && ($status = $dispatcher->error()) !== null) {
        $errorHandler
            ->handle($status, $dispatcher->getRequest(), $dispatcher->getResponse())
            ->send();
    }
} catch (\Throwable $e) {
    error_log($e->getMessage());
    $errorHandler
        ->handle(500, $dispatcher->getRequest(), $dispatcher->getResponse())
        ->send();
}
Full index.php Template
php
<?php

declare(strict_types=1);

ob_start();

use SulCompany\Router\Contracts\ErrorHandlerInterface;
use SulCompany\Router\DefaultErrorHandler;
use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../bootstrap/app.php';
$router = $container->get(Router::class);

// ... register routes ...

$errorHandler = $container->has(ErrorHandlerInterface::class)
    ? $container->get(ErrorHandlerInterface::class)
    : new DefaultErrorHandler();

$dispatcher = new Dispatcher($router, $container);

try {
    $ok = $dispatcher->dispatch();

    if (!$ok && ($status = $dispatcher->error()) !== null) {
        $errorHandler
            ->handle($status, $dispatcher->getRequest(), $dispatcher->getResponse())
            ->send();
    }
} catch (\Throwable $e) {
    error_log($e->getMessage());
    $errorHandler
        ->handle(500, $dispatcher->getRequest(), $dispatcher->getResponse())
        ->send();
}

while (ob_get_level() > 0) {
    ob_end_flush();
}
How the Dispatcher Handles Errors
The Dispatcher treats errors in three categories:

1. Success — dispatch() returns true
The controller ran, the Response is set, and the Dispatcher sent it.

php
if ($dispatcher->dispatch()) {
    // Response was sent
}
2. Middleware Stopped — dispatch() returns false with a built Response
The middleware set the Response and returned false. The Dispatcher sent that response.

php
if (!$ok && $dispatcher->error() === 401) {
    // The 401 response was already sent by the middleware
}
Important: in this case, the ErrorHandler is not invoked. The middleware is responsible for the response.

3. Routing Error — dispatch() returns false with an error code
The route/method/controller was not found. The Dispatcher did not send anything. The caller (via ErrorHandler) must handle it.

Error	error()	Meaning
Route not found	404	No route matches the path
Method not allowed	405	Path exists, but the HTTP method is wrong
Controller not found	400	Handler is invalid
Method missing	405	Method doesn't exist on the controller
Summary Table
Situation	dispatch()	error()	Sent?	Handler used?
Route OK	true	null	✅	❌
Middleware stopped	false	401/403/302	✅	❌
Route not found	false	404	❌	✅
Controller invalid	false	400	❌	✅
Method missing	false	405	❌	✅
Exception	—	—	❌	✅ (via catch)
Status Code Reference
The Router uses standard HTTP status codes:

Code	Meaning	Set by
200	OK	Default
201	Created	Controller
204	No Content	Controller
301	Moved Permanently	redirect(..., 301)
302	Found (temp redirect)	redirect() / back()
400	Bad Request	Router (controller not found)
401	Unauthorized	Middleware
403	Forbidden	Middleware
404	Not Found	Router
405	Method Not Allowed	Router
419	Session Expired	App (CSRF)
429	Too Many Requests	Middleware
500	Internal Server Error	App (catch)
501	Not Implemented	Router
503	Service Unavailable	App (catch)
Dispatcher Constants
php
Dispatcher::BAD_REQUEST;          // 400
Dispatcher::UNAUTHORIZED;         // 401
Dispatcher::FORBIDDEN;            // 403
Dispatcher::NOT_FOUND;            // 404
Dispatcher::METHOD_NOT_ALLOWED;   // 405
Dispatcher::SERVER_ERROR;         // 500
Dispatcher::NOT_IMPLEMENTED;      // 501
Exceptions
The Router does not catch exceptions. Exceptions are the application's responsibility.

Why?
Because the Router can't know:

What to log (domain-specific)

Where to log (file, Sentry, CloudWatch...)

What status to use (500? 503?)

How to render (HTML? JSON?)

Handling in index.php
php
try {
    $ok = $dispatcher->dispatch();
    // ...
} catch (DatabaseConnectionException $e) {
    $logger->channel('database')->critical('DB down', ['exception' => $e]);
    $errorHandler->handle(503, $request, $response)->send();
} catch (DatabaseException $e) {
    $logger->channel('database')->critical('DB error', ['exception' => $e]);
    $errorHandler->handle(503, $request, $response)->send();
} catch (\Throwable $e) {
    $logger->channel('app')->critical('Unexpected error', ['exception' => $e]);
    $errorHandler->handle(500, $request, $response)->send();
}
Common Exception Types
Exception	Status	Meaning
InvalidArgumentException	400	Bad input
AuthenticationException	401	Auth failed
AuthorizationException	403	Not allowed
ModelNotFoundException	404	Resource not found
ValidationException	422	Validation failed
DatabaseConnectionException	503	DB unreachable
DatabaseException	503	Query error
\Throwable	500	Any other error
Wrap in try/catch
The safest pattern:

php
$dispatcher = null;

try {
    $dispatcher = new Dispatcher($router, $container);
    $ok = $dispatcher->dispatch();
    // ...
} catch (\Throwable $e) {
    $request = $dispatcher?->getRequest() ?? new Request($_SERVER, $_GET ?? [], $_POST ?? []);
    $response = $dispatcher?->getResponse() ?? new Response();
    $errorHandler->handle(500, $request, $response)->send();
}
Note: $dispatcher may be null if the exception happened before it was created.

Common Patterns
JSON vs HTML
php
public function handle(int $status, Request $request, Response $response): Response
{
    if ($request->expectsJson()) {
        return $response->json(['error' => $status], $status);
    }

    return $response->html("<h1>Error {$status}</h1>", $status);
}
Context-Aware Views
php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
$view = "{$context}/errors/{$status}";

return $response->html($this->view->render($view), $status);
Localized Messages
php
private function message(int $status): string
{
    return match ($status) {
        404 => __('errors.not_found'),
        500 => __('errors.server_error'),
        default => __('errors.generic'),
    };
}
Fallback View
php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
$candidates = [
    "{$context}/errors/{$status}",
    "errors/{$status}",
    "errors/default",
];

foreach ($candidates as $view) {
    if ($this->view->exists($view)) {
        return $response->html($this->view->render($view, ['status' => $status]), $status);
    }
}

// Last resort: inline HTML
return $response->html("<h1>{$status}</h1>", $status);
Logging in the Handler
php
public function handle(int $status, Request $request, Response $response): Response
{
    if ($status >= 500) {
        $this->logger->error("HTTP {$status}", [
            'path' => $request->path(),
            'ip'   => $request->ip(),
        ]);
    }

    // ... build response ...
}
Common Errors
ErrorHandlerInterface Not Registered
Symptom: the fallback DefaultErrorHandler is used instead of your custom handler.

Cause: the interface isn't bound in the container.

Fix:

php
$container->singleton(ErrorHandlerInterface::class, function ($c) {
    return new AppErrorHandler($c->get(View::class));
});
Custom Handler Not Called
Symptom: errors show the default text (English) instead of your custom view.

Cause: the index.php uses new DefaultErrorHandler() directly, ignoring the container.

Fix:

php
$errorHandler = $container->has(ErrorHandlerInterface::class)
    ? $container->get(ErrorHandlerInterface::class)
    : new DefaultErrorHandler();
500 Not Caught
Symptom: an exception is thrown in a controller, but the error page is not shown.

Cause: the try/catch in index.php is missing or doesn't cover the exception type.

Fix:

php
try {
    // ...
} catch (\Throwable $e) {
    $errorHandler->handle(500, ...)->send();
}
Error Handler send() Called Twice
Symptom: output duplication.

Cause: the send() method is called both inside the handler and again after.

Fix: send() is idempotent — the second call is a no-op. But check your flow. The handler should return the Response, not send it.

Error View Not Found
Symptom: TemplateNotFoundException when the handler tries to render.

Cause: the view file doesn't exist for that status/context.

Fix: add a fallback chain (see Fallback View).

See Also
Request — expectsJson(), route(), getAttribute()

Response — building error responses

Middleware — 401/403 patterns

Container — registering the handler

← Back to README | 🇵🇹 Português