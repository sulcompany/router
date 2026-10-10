# Tratamento de Erros

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/error-handling.md)

O Router trata **erros de roteamento** (404, 405, 400) e deixa a aplicação tratar **erros de aplicação** (500, 503) através de um contrato simples: `ErrorHandlerInterface`.

---

## Índice

- [Visão Geral](#visão-geral)
- [O Contrato](#o-contrato)
- [DefaultErrorHandler](#defaulterrorhandler)
- [Handler Customizado](#handler-customizado)
- [Usando o Handler](#usando-o-handler)
- [Como o Dispatcher Trata Erros](#como-o-dispatcher-trata-erros)
- [Referência de Códigos de Status](#referência-de-códigos-de-status)
- [Exceções](#exceções)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Visão Geral

O Router divide o tratamento de erros em **duas camadas**:

| Camada | Responsabilidade | Exemplos |
|--------|------------------|----------|
| **Router** | Detectar erros de roteamento, fornecer um handler padrão | 404, 405, 400 |
| **Aplicação** | Decidir o que o usuário vê, logging, views customizadas | 500, 503, 404 customizado |

**Princípio chave:** o Router **não** sabe o que é uma "view". Ele só conhece:

- O código de status
- Se o cliente quer JSON
- A rota correspondida (com atributo `context`)

A aplicação implementa `ErrorHandlerInterface` para decidir como renderizar o erro.

---

## O Contrato

```php
namespace SulCompany\Router\Contracts;

use SulCompany\Router\Request;
use SulCompany\Router\Response;

interface ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response;
}
```

### Regras Importantes

1. **O handler NÃO envia a response** — ele apenas **retorna**
2. **O caller envia** — seja o `Dispatcher` ou o `index.php`
3. **O handler tem acesso a** — o status, o `Request`, e uma `Response` fresca

### Por Que Retornar, Não Enviar?

Porque enviar é o **último passo**. O caller pode querer:

- Adicionar headers (tipo middleware)
- Logar a response
- Enviar numa ordem específica

Retornar mantém o handler **puro** e **testável**.

---

## DefaultErrorHandler

O Router inclui um fallback para quando a app não registra um.

```php
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
```

### Comportamento

| Cliente | Response |
|---------|----------|
| JSON / AJAX | `{"success":false,"code":404,"message":"Not Found"}` |
| Browser | `404 — Not Found` (text/plain) |

### Exemplo: JSON

```json
{
    "success": false,
    "code": 404,
    "message": "Not Found"
}
```

### Exemplo: HTML

```
404 — Not Found
```

**Nota:** o handler padrão retorna `text/plain`, não HTML. Aplicações que querem páginas HTML devem implementar seu próprio handler.

### Quando é Usado

O `DefaultErrorHandler` é usado quando:

1. **Nenhum handler está registrado** no container
2. **O `index.php` faz fallback** para `new DefaultErrorHandler()`

O `Dispatcher` **não** o chama automaticamente — o caller faz.

---

## Handler Customizado

Implemente `ErrorHandlerInterface` na sua aplicação.

### Exemplo Mínimo

```php
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
                'message' => "Erro {$status}",
            ], $status);
        }

        return $response->html("<h1>Erro {$status}</h1>", $status);
    }
}
```

### Com Views (Plates, Twig, Blade...)

```php
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

        // HTML — escolhe view baseado no contexto
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
```

### Usando `context`

O atributo `context` é definido por `group(context: 'admin')` e herdado.

```php
$router->group('/admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index']);
});

// Erro em rotas /admin/* → contexto = 'admin' → view "admin/errors/404"
```

| Rota | Contexto | View |
|------|----------|------|
| `/users` (sem grupo) | `ecommerce` (default) | `ecommerce/errors/404` |
| `/admin/users` | `admin` | `admin/errors/404` |

---

## Usando o Handler

### Registre no Container

```php
// bootstrap/app.php ou um provider
$container->singleton(ErrorHandlerInterface::class, function ($c) {
    return new AppErrorHandler($c->get(View::class));
});
```

### Chame no `index.php`

```php
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
```

### Template Completo do `index.php`

```php
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

// ... registre rotas ...

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
```

---

## Como o Dispatcher Trata Erros

O `Dispatcher` trata erros em **três categorias**:

### 1. Sucesso — `dispatch()` retorna `true`

O controller rodou, a `Response` está definida, e o `Dispatcher` **enviou**.

```php
if ($dispatcher->dispatch()) {
    // Response foi enviada
}
```

### 2. Middleware Parou — `dispatch()` retorna `false` com uma Response construída

O middleware definiu a `Response` e retornou `false`. O `Dispatcher` **enviou** essa response.

```php
if (!$ok && $dispatcher->error() === 401) {
    // A response 401 já foi enviada pelo middleware
}
```

**Importante:** neste caso, o `ErrorHandler` **não** é invocado. O middleware é responsável pela response.

### 3. Erro de Roteamento — `dispatch()` retorna `false` com um código de erro

A rota/método/controller não foi encontrado. O `Dispatcher` **não enviou** nada. O caller (via `ErrorHandler`) deve tratar.

| Erro | `error()` | Significado |
|------|-----------|-------------|
| Rota não encontrada | `404` | Nenhuma rota corresponde ao caminho |
| Método não permitido | `405` | Caminho existe, mas o método HTTP está errado |
| Controller não encontrado | `400` | Handler inválido |
| Método ausente | `405` | Método não existe no controller |

### Tabela Resumo

| Situação | `dispatch()` | `error()` | Enviada? | Handler usado? |
|----------|--------------|-----------|----------|----------------|
| Rota OK | `true` | `null` | ✅ | ❌ |
| Middleware parou | `false` | `401`/`403`/`302` | ✅ | ❌ |
| Rota não encontrada | `false` | `404` | ❌ | ✅ |
| Controller inválido | `false` | `400` | ❌ | ✅ |
| Método ausente | `false` | `405` | ❌ | ✅ |
| Exceção | — | — | ❌ | ✅ (via `catch`) |

---

## Referência de Códigos de Status

O Router usa códigos HTTP padrão:

| Código | Significado | Definido por |
|--------|-------------|--------------|
| `200` | OK | Padrão |
| `201` | Created | Controller |
| `204` | No Content | Controller |
| `301` | Moved Permanently | `redirect(..., 301)` |
| `302` | Found (redirect temp) | `redirect()` / `back()` |
| `400` | Bad Request | Router (controller não encontrado) |
| `401` | Unauthorized | Middleware |
| `403` | Forbidden | Middleware |
| `404` | Not Found | Router |
| `405` | Method Not Allowed | Router |
| `419` | Session Expired | App (CSRF) |
| `429` | Too Many Requests | Middleware |
| `500` | Internal Server Error | App (`catch`) |
| `501` | Not Implemented | Router |
| `503` | Service Unavailable | App (`catch`) |

### Constantes do Dispatcher

```php
Dispatcher::BAD_REQUEST;          // 400
Dispatcher::UNAUTHORIZED;         // 401
Dispatcher::FORBIDDEN;            // 403
Dispatcher::NOT_FOUND;            // 404
Dispatcher::METHOD_NOT_ALLOWED;   // 405
Dispatcher::SERVER_ERROR;         // 500
Dispatcher::NOT_IMPLEMENTED;      // 501
```

---

## Exceções

O Router **não captura exceções**. Exceções são responsabilidade da aplicação.

### Por Quê?

Porque o Router não pode saber:

- O que logar (específico do domínio)
- Onde logar (arquivo, Sentry, CloudWatch...)
- Que status usar (500? 503?)
- Como renderizar (HTML? JSON?)

### Tratando no `index.php`

```php
try {
    $ok = $dispatcher->dispatch();
    // ...
} catch (DatabaseConnectionException $e) {
    $logger->channel('database')->critical('DB fora', ['exception' => $e]);
    $errorHandler->handle(503, $request, $response)->send();
} catch (DatabaseException $e) {
    $logger->channel('database')->critical('Erro DB', ['exception' => $e]);
    $errorHandler->handle(503, $request, $response)->send();
} catch (\Throwable $e) {
    $logger->channel('app')->critical('Erro inesperado', ['exception' => $e]);
    $errorHandler->handle(500, $request, $response)->send();
}
```

### Tipos Comuns de Exceção

| Exceção | Status | Significado |
|---------|--------|-------------|
| `InvalidArgumentException` | 400 | Entrada inválida |
| `AuthenticationException` | 401 | Auth falhou |
| `AuthorizationException` | 403 | Não permitido |
| `ModelNotFoundException` | 404 | Recurso não encontrado |
| `ValidationException` | 422 | Validação falhou |
| `DatabaseConnectionException` | 503 | DB inacessível |
| `DatabaseException` | 503 | Erro de query |
| `\Throwable` | 500 | Qualquer outro erro |

### Envolver em `try/catch`

O padrão mais seguro:

```php
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
```

**Nota:** `$dispatcher` pode ser `null` se a exceção aconteceu **antes** de ser criado.

---

## Padrões Comuns

### JSON vs HTML

```php
public function handle(int $status, Request $request, Response $response): Response
{
    if ($request->expectsJson()) {
        return $response->json(['error' => $status], $status);
    }

    return $response->html("<h1>Erro {$status}</h1>", $status);
}
```

### Views Sensíveis ao Contexto

```php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
$view = "{$context}/errors/{$status}";

return $response->html($this->view->render($view), $status);
```

### Mensagens Localizadas

```php
private function message(int $status): string
{
    return match ($status) {
        404 => __('errors.not_found'),
        500 => __('errors.server_error'),
        default => __('errors.generic'),
    };
}
```

### View de Fallback

```php
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

// Último recurso: HTML inline
return $response->html("<h1>{$status}</h1>", $status);
```

### Logging no Handler

```php
public function handle(int $status, Request $request, Response $response): Response
{
    if ($status >= 500) {
        $this->logger->error("HTTP {$status}", [
            'path' => $request->path(),
            'ip'   => $request->ip(),
        ]);
    }

    // ... constrói response ...
}
```

---

## Erros Comuns

### `ErrorHandlerInterface` Não Registrado

**Sintoma:** o fallback `DefaultErrorHandler` é usado em vez do seu handler customizado.

**Causa:** a interface não está vinculada no container.

**Correção:**

```php
$container->singleton(ErrorHandlerInterface::class, function ($c) {
    return new AppErrorHandler($c->get(View::class));
});
```

### Handler Customizado Não É Chamado

**Sintoma:** erros mostram o texto padrão (inglês) em vez da sua view customizada.

**Causa:** o `index.php` usa `new DefaultErrorHandler()` diretamente, ignorando o container.

**Correção:**

```php
$errorHandler = $container->has(ErrorHandlerInterface::class)
    ? $container->get(ErrorHandlerInterface::class)
    : new DefaultErrorHandler();
```

### 500 Não Capturado

**Sintoma:** uma exceção é lançada num controller, mas a página de erro não é mostrada.

**Causa:** o `try/catch` no `index.php` está faltando ou não cobre o tipo de exceção.

**Correção:**

```php
try {
    // ...
} catch (\Throwable $e) {
    $errorHandler->handle(500, ...)->send();
}
```

### `send()` do Error Handler Chamado Duas Vezes

**Sintoma:** duplicação de output.

**Causa:** o método `send()` é chamado tanto dentro do handler quanto depois.

**Correção:** `send()` é **idempotente** — a segunda chamada não faz nada. Mas verifique seu fluxo. O handler deve **retornar** a Response, não enviar.

### View de Erro Não Encontrada

**Sintoma:** `TemplateNotFoundException` quando o handler tenta renderizar.

**Causa:** o arquivo de view não existe para aquele status/contexto.

**Correção:** adicione uma cadeia de fallback (veja [View de Fallback](#view-de-fallback)).

---

## Veja Também

- [Request](request.md) — `expectsJson()`, `route()`, `getAttribute()`
- [Response](response.md) — construindo responses de erro
- [Middleware](middleware.md) — padrões 401/403
- [Container](container.md) — registrando o handler

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/error-handling.md)