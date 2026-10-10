# SulCompany Router

> Um roteador PHP leve, sem dependências, com rotas dinâmicas, middleware, grupos de rotas, contextos e tratamento de erros integrado.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PSR-11](https://img.shields.io/badge/PSR--11-compliant-brightgreen)](https://www.php-fig.org/psr/psr-11/)
[![Latest Version](https://img.shields.io/packagist/v/sulcompany/router)](https://packagist.org/packages/sulcompany/router)
[![Total Downloads](https://img.shields.io/packagist/dt/sulcompany/router)](https://packagist.org/packages/sulcompany/router)

[🇺🇸 English](README.md) | 🇵🇹 **Português**

---

## 📖 Visão Geral

O **SulCompany Router** é um mini-framework de roteamento para PHP 8.1+. **Não** é PSR-7, **não** é FastRoute. É uma alternativa **simples, direta e opinativa** para desenvolvedores que querem:

- Roteamento HTTP
- Pipeline de middleware
- Grupos de rotas com prefixo, nome e contexto
- Tratamento de erros configurável
- Container PSR-11 integrado (opcional)
- **Zero dependências além de `psr/container`**

Foi construído para ser **previsível**, **legível** e **extensível** — sem o peso de um framework completo.

---

## ✨ Funcionalidades

- ✅ Rotas REST (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
- ✅ Parâmetros dinâmicos (`/user/{id}`)
- ✅ Parâmetros wildcard (`/files/{path*}`)
- ✅ Rotas nomeadas e geração de URLs
- ✅ Grupos aninhados (prefixo + nome + contexto + middleware)
- ✅ Contextos (`admin`, `ecommerce`, ...) para views de erro diferenciadas
- ✅ Pipeline de middleware `before` / `after`
- ✅ Container PSR-11 com autowiring
- ✅ Cache de rotas (via `var_export`)
- ✅ Tratamento de erros via `ErrorHandlerInterface`
- ✅ Detecção automática de JSON / AJAX
- ✅ Sem dependências pesadas

---

## 📋 Requisitos

| Requisito | Versão |
|-----------|--------|
| **PHP** | `^8.1` |
| **Composer** | `^2.0` |
| **psr/container** | `^2.0` (instalado automaticamente) |

---

## 🚀 Instalação

```bash
composer require sulcompany/router
```

---

## ⚡ Quick Start

### 1. Crie `public/index.php`

```php
<?php

declare(strict_types=1);

ob_start();

use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://meusite.com');

$router->get('/', function ($request, $response) {
    return $response->html('<h1>Olá, mundo!</h1>');
}, name: 'home');

$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();

while (ob_get_level() > 0) {
    ob_end_flush();
}
```

### 2. Teste

- `GET /` → retorna HTML
- `GET /user/42` → executa `UserController::show('42')`

**Pronto.** 🎉

---

## 🧠 O Básico

### Rotas

```php
$router->get('/about', [SiteController::class, 'about'], name: 'site.about');
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');
```

📖 [Documentação completa de rotas →](docs/pt/routes.md)

### Grupos

```php
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

📖 [Documentação completa de grupos →](docs/pt/groups.md)

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

📖 [Documentação completa de middleware →](docs/pt/middleware.md)

### Controllers

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
```

📖 [Documentação completa de controllers →](docs/pt/controllers.md)

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

📖 [Documentação completa de Request →](docs/pt/request.md)

### Response

```php
return $response->html('<h1>Olá</h1>');
return $response->json(['ok' => true]);
return $response->redirect('/login');
```

📖 [Documentação completa de Response →](docs/pt/response.md)

### Tratamento de Erros

```php
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        if ($request->expectsJson()) {
            return $response->json(['error' => true], $status);
        }
        return $response->html("<h1>Erro {$status}</h1>", $status);
    }
}
```

📖 [Documentação completa de tratamento de erros →](docs/pt/error-handling.md)

### Container (DI)

```php
$container = new Container();
$container->singleton(PDO::class, fn() => new PDO('mysql:...'));
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
```

📖 [Documentação completa de container →](docs/pt/container.md)

### Cache de Rotas

```php
$router->enableCache(__DIR__ . '/cache/routes.php');
if (!$router->loadCache()) {
    $router->get('/', ...);
    $router->compile();
    $router->cacheCompiledRoutes();
}
```

📖 [Documentação completa de cache →](docs/pt/cache.md)

### Geração de URL

```php
$router->urlFor('user.show', ['id' => 42]);
// → https://meusite.com/user/42
```

📖 [Documentação completa de geração de URL →](docs/pt/url-generation.md)

---

## 📚 Documentação

| Documento | Descrição |
|-----------|-----------|
| [Rotas](docs/pt/routes.md) | Métodos HTTP, parâmetros dinâmicos, wildcards |
| [Grupos](docs/pt/groups.md) | Prefixo, nome, contexto, middleware, aninhamento |
| [Middleware](docs/pt/middleware.md) | Before/after, tipos, ordem |
| [Controllers](docs/pt/controllers.md) | Closures, classes, DI |
| [Request](docs/pt/request.md) | Todos os métodos de request |
| [Response](docs/pt/response.md) | Todos os builders de response |
| [Tratamento de Erros](docs/pt/error-handling.md) | Contrato, default, customizado |
| [Container](docs/pt/container.md) | PSR-11, bind, singleton, autowiring |
| [Cache](docs/pt/cache.md) | Compilação e cache de rotas |
| [Geração de URL](docs/pt/url-generation.md) | Rotas nomeadas e urlFor() |

📖 [🇺🇸 Documentation in English →](docs/en/)

---

## ⚠️ Notas Importantes

### Output Buffering

```php
ob_start();           // no topo

// ... seu código ...

while (ob_get_level() > 0) {
    ob_end_flush();   // no final
}
```

Sem isso, suas páginas podem renderizar **em branco** com `content-length: 0`.

### Exceções

O Router **não** captura exceções. Use `try/catch` no seu `index.php`.

### Não é PSR-7

`Request` e `Response` são mutáveis e minimalistas.

### Container PSR-11

A classe `Container` implementa `Psr\Container\ContainerInterface`.

---

## 🧩 Compatibilidade

| PHP | Suportado |
|-----|-----------|
| 8.1 | ✅ |
| 8.2 | ✅ |
| 8.3 | ✅ |
| 8.4 | ✅ (ainda não testado) |
| 7.x | ❌ |

---

## 🤝 Contribuindo

1. Faça um fork do repositório
2. Crie um branch de feature
3. Faça commit das mudanças
4. Faça push para o branch
5. Abra um Pull Request

---

## 🐛 Reportando Problemas

[Abra uma issue](https://github.com/sulcompany/router/issues) com versão do PHP, versão do Router e uma reprodução mínima.

---

## 👥 Créditos

- **Francisco Dulo** — autor
- **Sul Company Lda** — mantenedor
- **Contribuidores** — todos que ajudaram

---

## 📄 Licença

MIT — veja [LICENSE](LICENSE).

---

**SulCompany Router** — feito com 🖤 em Angola.