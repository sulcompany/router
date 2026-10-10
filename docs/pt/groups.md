# Grupos

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/groups.md)

Grupos permitem organizar rotas com um **prefixo compartilhado**, **prefixo de nome**, **contexto** e **middleware**. Podem ser aninhados, e toda propriedade é herdada pelas rotas filhas.

---

## Índice

- [Assinatura](#assinatura)
- [Grupo Básico](#grupo-básico)
- [Grupo com Nome](#grupo-com-nome)
- [Grupo com Contexto](#grupo-com-contexto)
- [Grupo com Middleware](#grupo-com-middleware)
- [Exemplo Completo](#exemplo-completo)
- [Grupos Aninhados](#grupos-aninhados)
- [Regras de Herança](#regras-de-herança)
- [Normalização de Prefixo](#normalização-de-prefixo)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Assinatura

```php
public function group(
    string $prefix,
    callable $callback,
    array|string|null $middleware = null,
    ?string $name = null,
    ?string $context = null
): self;
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `$prefix` | `string` | ✅ | Prefixo de URL (ex.: `/admin`) |
| `$callback` | `callable` | ✅ | Callback que recebe o Router |
| `$middleware` | `array\|string\|null` | ❌ | Middleware do grupo |
| `$name` | `?string` | ❌ | Prefixo de nome (ex.: `admin`) |
| `$context` | `?string` | ❌ | Atributo de contexto (ex.: `admin`) |

### Argumentos Nomeados (Recomendado)

Como a lista de parâmetros é longa, use **argumentos nomeados** para clareza:

```php
$router->group(
    prefix: '/admin',
    name: 'admin',
    context: 'admin',
    middleware: [AuthAdminMiddleware::class],
    callback: function ($r) {
        // rotas aqui
    }
);
```

---

## Grupo Básico

Apenas um prefixo:

```php
$router->group('/admin', function ($r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

**Rotas resultantes:**

| Caminho final | Nome final | Contexto |
|---------------|-----------|----------|
| `/admin/dashboard` | `dashboard` | `ecommerce` (default) |
| `/admin/users` | `users.index` | `ecommerce` (default) |

**Nota:** o nome **não** é prefixado a menos que você passe `name`.

---

## Grupo com Nome

Adicione `name` para prefixar todos os nomes de rotas dentro:

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

**Rota resultante:**

| Caminho final | Nome final |
|---------------|-----------|
| `/admin/users` | `admin.users.index` |

### Por Que Prefixar Nome Importa

A geração de URL usa o **nome final**:

```php
$router->urlFor('admin.users.index');
// → https://meusite.com/admin/users
```

### Prefixos de Nome Aninhados

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
        // nome final: admin.users.list
    });
});
```

---

## Grupo com Contexto

Adicione `context` para marcar todas as rotas dentro. Este atributo é **lido pelo error handler** para escolher a view de erro correta.

```php
$router->group('/admin', name: 'admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

**Rota resultante:**

| Caminho final | Nome final | Contexto |
|---------------|-----------|----------|
| `/admin/users` | `admin.users.index` | `admin` |

### Contexto Padrão

Se nenhum `context` for declarado em nenhum lugar da cadeia, `Router::DEFAULT_CONTEXT` é usado (`ecommerce`).

### Lendo o Contexto

```php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
```

### Exemplo: Views de Erro

```php
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        $context = $request->route()?->getAttribute('context') ?? 'ecommerce';
        $view = "{$context}/errors/{$status}";

        return $response->html($this->view->render($view, ['status' => $status]), $status);
    }
}
```

📖 [Documentação completa de tratamento de erros →](error-handling.md)

---

## Grupo com Middleware

Adicione `middleware` para rodar em todas as rotas do grupo:

```php
$router->group(
    '/admin',
    middleware: [AuthAdminMiddleware::class, CsrfMiddleware::class],
    callback: function ($r) {
        $r->get('/dashboard', [AdminController::class, 'dashboard']);
    }
);
```

### Múltiplos Middleware

Passe um array para aplicar em ordem:

```php
$router->group('/admin', middleware: [
    LogRequestsMiddleware::class,
    AuthAdminMiddleware::class,
    CsrfMiddleware::class,
], callback: function ($r) {
    // ...
});
```

**Ordem de execução:**

1. Middleware global
2. Middleware do grupo (na ordem do array)
3. Middleware da rota

📖 [Documentação completa de middleware →](middleware.md)

---

## Exemplo Completo

Combine tudo:

```php
$router->group(
    prefix: '/admin',
    name: 'admin',
    context: 'admin',
    middleware: [AuthAdminMiddleware::class],
    callback: function ($r) {
        $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
        $r->get('/users', [UserController::class, 'index'], name: 'users.index');
        $r->get('/settings', [SettingsController::class, 'index'], name: 'settings.index');
    }
);
```

**Rotas resultantes:**

| Caminho | Nome | Contexto | Middleware |
|---------|------|----------|------------|
| `/admin/dashboard` | `admin.dashboard` | `admin` | global + `AuthAdminMiddleware` |
| `/admin/users` | `admin.users.index` | `admin` | global + `AuthAdminMiddleware` |
| `/admin/settings` | `admin.settings.index` | `admin` | global + `AuthAdminMiddleware` |

---

## Grupos Aninhados

Grupos podem ser aninhados em qualquer profundidade. Prefixos, nomes, middleware e contexto **cascateiam para baixo**.

```php
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
        $r2->get('/{id}', [UserController::class, 'show'], name: 'show');
        $r2->group('/roles', name: 'roles', callback: function ($r3) {
            $r3->get('/', [RoleController::class, 'index'], name: 'index');
        });
    });
});
```

**Rotas resultantes:**

| Caminho | Nome | Contexto |
|---------|------|----------|
| `/admin/users/list` | `admin.users.list` | `admin` |
| `/admin/users/{id}` | `admin.users.show` | `admin` |
| `/admin/users/roles` | `admin.users.roles.index` | `admin` |

**Middleware:** `AuthAdmin::class` é aplicado a **todas** as rotas aninhadas.

---

## Regras de Herança

### Prefixo

Concatenado com o prefixo do pai:

```
pai:    /admin
filho:  /users
final:  /admin/users
```

### Nome

Concatenado com um separador `.`:

```
pai:    admin
filho:  users
final:  admin.users
```

### Middleware

Mesclado (pai primeiro, depois filho):

```
pai:    [AuthAdmin]
filho:  [Csrf]
final:  [AuthAdmin, Csrf]
```

### Contexto

Herdado **a menos que** o filho sobrescreva:

```
pai:    admin
filho:  (nenhum)    → admin   (herdado)
filho:  ecommerce   → ecommerce (sobrescrito)
```

### Tabela Completa de Herança

| Propriedade | Herança |
|-------------|---------|
| `prefix` | Concatenado |
| `name` | Concatenado com `.` |
| `middleware` | Mesclado (pai primeiro) |
| `context` | Herdado, sobrescrevível |

---

## Normalização de Prefixo

O Router normaliza prefixos durante o registro:

| Entrada | Prefixo resultante |
|---------|-------------------|
| `/admin` | `/admin` |
| `admin` | `/admin` |
| `admin/` | `/admin` |
| `/admin/` | `/admin` |
| `''` | `''` (vazio) |

### Prefixo com Caminho de Rota

| Prefixo do grupo | Caminho da rota | Caminho final |
|------------------|-----------------|---------------|
| `/admin` | `/users` | `/admin/users` |
| `/admin` | `users` | `/admin/users` |
| `/admin` | `/` | `/admin` |
| `/admin` | `''` | `/admin` |
| `''` (vazio) | `/users` | `/users` |

---

## Padrões Comuns

### Ecommerce vs Admin

```php
// Ecommerce — raiz, sem contexto (cai no DEFAULT_CONTEXT)
$router->get('/cart', [CartController::class, 'index'], name: 'ecommerce.cart.index');
$router->get('/checkout', [CheckoutController::class, 'index'], name: 'ecommerce.checkout');

// Admin — contexto explícito
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
```

### API com Versionamento

```php
$router->group('/api/v1', name: 'api.v1', context: 'api', middleware: [ApiKeyMiddleware::class], callback: function ($r) {
    $r->get('/users', [ApiUserController::class, 'index'], name: 'users.index');
});
```

### Account (sub-área do Ecommerce)

```php
$router->group('/account', name: 'account', callback: function ($r) {
    // contexto: ecommerce (herdado da raiz)
    $r->get('/profile', [AccountController::class, 'profile'], name: 'profile');
    $r->get('/orders', [AccountController::class, 'orders'], name: 'orders');
});
```

### Backoffice vs Front-office

```php
// Front-office (sem prefixo, sem contexto)
$router->get('/', [HomeController::class, 'index'], name: 'home');

// Backoffice (prefixado + contexto)
$router->group('/backoffice', name: 'backoffice', context: 'backoffice', callback: function ($r) {
    // ...
});
```

---

## Erros Comuns

### Contexto Não Aplicado à Rota

**Sintoma:** a view de erro de uma rota é sempre `ecommerce`, mesmo para `/admin/*`.

**Causa:** a rota não está dentro de um grupo com `context: 'admin'`.

**Correção:**

```php
$router->group('/admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index']);
});
```

### Nome Não Prefixado

**Sintoma:** `urlFor('users.index')` falha para uma rota dentro de `/admin`.

**Causa:** você esqueceu `name: 'admin'` no grupo.

**Correção:**

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    // nome final: admin.users.index
});
```

### Middleware Não Executando

**Sintoma:** um middleware do tipo global não é aplicado a um grupo.

**Causa:** middleware não está registrado no grupo.

**Correção:**

```php
$router->group('/admin', middleware: [AuthAdmin::class], callback: function ($r) {
    // ...
});
```

### Barra Dupla no Caminho

**Sintoma:** caminho da rota vira `/admin//users`.

**Causa:** prefixo e caminho da rota ambos terminam/começam com `/`.

**Correção:** o Router **normaliza** ambos — mas se usar strings cruas, evite barras duplas. O Router trata isso automaticamente.

---

## Exemplos

### Setup Completo Ecommerce + Admin

```php
// ── Ecommerce (raiz) ────────────────────────────────
$router->get('/', [HomeController::class, 'index'], name: 'ecommerce.home');
$router->get('/cart', [CartController::class, 'index'], name: 'ecommerce.cart.index');

$router->group('/account', name: 'ecommerce.account', callback: function ($r) {
    $r->get('/profile', [AccountController::class, 'profile'], name: 'profile');
    $r->get('/settings', [AccountController::class, 'settings'], name: 'settings');
});

// ── Admin ────────────────────────────────────────────
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    $r->get('/roles', [RoleController::class, 'index'], name: 'roles.index');

    $r->group('/settings', name: 'settings', callback: function ($r2) {
        $r2->get('/general', [SettingsController::class, 'general'], name: 'general');
    });
});
```

### Índice de Nomes Resultante

| Rota | Nome |
|------|------|
| `/` | `ecommerce.home` |
| `/cart` | `ecommerce.cart.index` |
| `/account/profile` | `ecommerce.account.profile` |
| `/admin/users` | `admin.users.index` |
| `/admin/roles` | `admin.roles.index` |
| `/admin/settings/general` | `admin.settings.general` |

---

## Veja Também

- [Rotas](routes.md) — registrando rotas
- [Middleware](middleware.md) — pipeline before/after
- [Tratamento de Erros](error-handling.md) — usando contexto em views de erro
- [Geração de URL](url-generation.md) — `urlFor()` com rotas nomeadas

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/groups.md)