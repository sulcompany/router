# Controllers

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/controllers.md)

Controllers lidam com a lógica de uma rota. O Router suporta dois tipos de handler: **closures** e **métodos de classe**. Esta página explica ambos, mais injeção de dependências e resolução de parâmetros.

---

## Índice

- [Tipos de Handler](#tipos-de-handler)
- [Closures](#closures)
- [Métodos de Classe](#métodos-de-classe)
- [Injeção de Dependências](#injeção-de-dependências)
- [Resolução de Parâmetros](#resolução-de-parâmetros)
- [Valores de Retorno](#valores-de-retorno)
- [Mutação vs Retorno da Response](#mutação-vs-retorno-da-response)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Tipos de Handler

| Tipo | Exemplo | Suporte a DI | Cacheável |
|------|---------|--------------|-----------|
| **Closure** | `fn($req, $res) => ...` | ❌ (apenas `$req`, `$res`, `$params`) | ❌ (ignorada no cache) |
| **Método de classe** | `[UserController::class, 'show']` | ✅ (DI completa) | ✅ |

**Recomendação:** use **métodos de classe** para qualquer coisa não trivial. Use **closures** para endpoints minúsculos (health checks, ping).

---

## Closures

Uma closure recebe **três argumentos**:

```php
$router->get('/health', function (Request $request, Response $response, array $params) {
    return $response->json(['status' => 'ok']);
});
```

### Tabela de Argumentos

| Argumento | Tipo | Descrição |
|-----------|------|-----------|
| `$request` | `Request` | A requisição atual |
| `$response` | `Response` | A response atual (já vazia) |
| `$params` | `array` | Parâmetros da rota (`{id}` → `['id' => '42']`) |

### Exemplos Mínimos

```php
// Sem argumentos
$router->get('/ping', fn() => (new Response())->text('pong'));

// Apenas request
$router->get('/who', fn($request) => (new Response())->text($request->ip()));

// Completo
$router->get('/user/{id}', function ($request, $response, $params) {
    return $response->json(['id' => $params['id']]);
});
```

### Limitações

- **Sem injeção de dependências** — apenas `$request`, `$response`, `$params`
- **Não cacheável** — closures não podem ser `var_export`-adas. Rotas com closures são **ignoradas** no cache de rotas.

---

## Métodos de Classe

Passe um array `[ClassName::class, 'method']`:

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

### O Que o Router Faz

1. **Resolve a classe** — via container ou autowiring
2. **Verifica se o método existe** — lança 405 se não
3. **Resolve parâmetros do método** — por tipo, depois por nome
4. **Invoca o método**
5. **Captura o valor de retorno** — se `Response`, usa; senão usa a `$response` injetada

### Métodos Estáticos

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

Funciona do mesmo jeito. O Router usa `ReflectionMethod::invokeArgs()`, que lida tanto com métodos estáticos quanto de instância.

### Controllers Abstratos / Interfaces

**Não suportado.** O Router precisa instanciar o controller. Classes abstratas e interfaces não podem ser instanciadas.

### Injeção pelo Construtor

```php
class UserController
{
    public function __construct(
        private UserRepository $repository,
        private LoggerInterface $logger
    ) {}

    public function show(string $id, Response $response): Response
    {
        $this->logger->info("Buscando usuário {$id}");
        $user = $this->repository->find($id);

        if (!$user) {
            return $response->json(['error' => 'Not Found'], 404);
        }

        return $response->json($user);
    }
}
```

O container resolve `UserRepository` e `LoggerInterface` automaticamente — **se registrados** ou **autowireable**.

---

## Injeção de Dependências

O Router resolve parâmetros do construtor e do método de **duas formas**:

### 1. Por Tipo

Se o parâmetro tem um tipo **não-builtin**, o Router resolve:

| Tipo | Resolvido para |
|------|----------------|
| `Request` | Instância atual de `Request` |
| `Response` | Instância atual de `Response` |
| Classe no container | Instância do container |
| Classe autowireable | Nova instância via reflection |

### 2. Por Nome

Se o nome do parâmetro corresponde a um **parâmetro da rota**, é injetado:

```php
$router->get('/user/{id}', [UserController::class, 'show']);
```

```php
public function show(string $id): Response  // $id vem de {id}
```

### Ordem de Resolução

Para cada parâmetro, o Router tenta:

1. **Match por tipo** → `Request`, `Response`, container, autowiring
2. **Match por nome** → parâmetro de rota com mesmo nome
3. **Dados da requisição** → `$request->all($name)`
4. Caso contrário → **`null`** (silencioso)

### Exemplos

```php
// Baseado em tipo
public function index(Request $request): Response

// Baseado em nome
public function show(string $id): Response

// Misto
public function update(Request $request, string $id): Response

// Com DI
public function store(CreateUserRequest $dto, Response $response): Response
```

### Fallback para Dados da Requisição

Se um parâmetro não é um tipo e não é parâmetro de rota, o Router procura nos dados da requisição (GET, POST, JSON):

```php
$router->post('/search', [SearchController::class, 'search']);
```

```php
public function search(string $q): Response  // $q vem de ?q=... ou POST
```

### Prioridade de Tipo

Quando um parâmetro tem um tipo E o nome bate com um parâmetro de rota, **o tipo vence**:

```php
public function show(Request $id): Response
// ❌ $id será o Request, não o parâmetro da rota
```

**Evite isso.** Use nomes claros:

```php
public function show(string $id, Request $request): Response
```

---

## Resolução de Parâmetros

### Tipos Builtin

| Tipo | Resolução |
|------|-----------|
| `string` | Parâmetro de rota ou dado da requisição |
| `int` | Parâmetro de rota (como string) ou dado da requisição |
| `float` | Parâmetro de rota (como string) ou dado da requisição |
| `bool` | Parâmetro de rota ou dado da requisição |
| `array` | Parâmetro de rota (wildcard) ou dado da requisição |

> ⚠️ **Nota:** o Router **não** faz cast de tipos. Se `{id}` é `"42"` (string) e o parâmetro é `int $id`, o PHP lança `TypeError` em strict mode.

**Correção:** declare o parâmetro como `string` e faça cast manualmente:

```php
public function show(string $id): Response
{
    $id = (int) $id;
    // ...
}
```

### Tipos de Classe

| Tipo | Resolução |
|------|-----------|
| `Request` | Requisição atual |
| `Response` | Response atual |
| Qualquer classe | Container → autowiring |
| `?Class` (nullable) | O mesmo acima, ou `null` |

### Parâmetros Wildcard

`{path*}` retorna um **array**:

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

### Parâmetros Ausentes

Se um parâmetro não pode ser resolvido, ele é **`null`**:

```php
public function show(string $id, string $missing): Response
// $missing será null (ou o PHP lança em strict mode)
```

**Dica:** declare tipos nullable ou forneça defaults.

---

## Valores de Retorno

### Retornando uma Response

**Recomendado.** O Router usa a Response retornada:

```php
public function index(Response $response): Response
{
    return $response->html('<h1>Olá</h1>');
}
```

### Não Retornando Nada

Se o método retorna `void` ou nada, a **`$response` injetada** é usada:

```php
public function index(Response $response): void
{
    $response->html('<h1>Olá</h1>');
}
```

**Ambos funcionam.** Retornar é mais limpo e mais fácil de testar.

### Retornando uma Response Diferente

O controller pode ignorar a `$response` injetada e criar a sua própria:

```php
public function index(): Response
{
    return (new Response())->json(['ok' => true]);
}
```

**Mas:** qualquer middleware que mutou a `$response` injetada **perde as mutações**.

### Retornos Não-Response

Se o método retorna algo que **não** é `Response`, o valor é **ignorado**:

```php
public function index(Response $response): array
{
    return ['ok' => true];  // ❌ ignorado
}
```

**Sempre retorne uma `Response`** (ou nada).

---

## Mutação vs Retorno da Response

### A Regra

- Se o controller **retorna uma `Response`** → essa Response é usada
- Se o controller **não retorna nada** → a `$response` injetada é usada

### Impacto do Middleware

```php
// Middleware define um header
public function before(Request $request, Response $response): bool
{
    $response->header('X-App', 'SulCompany');
    return true;
}
```

**Caso A — controller retorna a response injetada:**

```php
public function index(Response $response): Response
{
    return $response->html('<h1>Olá</h1>');
}
```

**Resultado:** header `X-App: SulCompany` é **preservado**. ✅

**Caso B — controller retorna uma NOVA response:**

```php
public function index(): Response
{
    return (new Response())->html('<h1>Olá</h1>');
}
```

**Resultado:** header `X-App` é **PERDIDO**. ❌

**Regra:** sempre `return $response->...` com a response **injetada**, a menos que saiba o que está fazendo.

---

## Padrões Comuns

### Controller RESTful de Recurso

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

### Form + Resposta API

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

### Download de Arquivo

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

### Redirect de Volta

```php
public function store(Request $request, Response $response): Response
{
    $this->users->create($request->post());

    return $response->back();  // redireciona para HTTP_REFERER
}
```

---

## Erros Comuns

### Classe Não Encontrada

**Sintoma:** `Error: Class "UserController" not found.`

**Causa:** namespace errado ou classe não existe.

**Correção:** verifique o namespace. Use a constante `::class`:

```php
$router->get('/users', [\App\Http\Controllers\UserController::class, 'index']);
```

### Método Não Encontrado

**Sintoma:** 405 Method Not Allowed.

**Causa:** o método não existe no controller.

**Correção:** verifique ortografia e visibilidade (`public`).

### Não é Possível Instanciar Classe Abstrata

**Sintoma:** `Error: Cannot instantiate abstract class Foo.`

**Causa:** o container tenta `new` numa classe abstrata ou interface.

**Correção:** registre a classe concreta no container:

```php
$container->bind(SomeInterface::class, SomeConcrete::class);
```

### Parâmetro Não Resolvível

**Sintoma:** `Error: Cannot resolve parameter $foo.`

**Causa:** o parâmetro tem um tipo de classe que não pode ser instanciado.

**Correção:** registre no container ou forneça valor default.

### Tipo Incompatível

**Sintoma:** `TypeError: Argument $id must be of type int, string given.`

**Causa:** PHP strict types + parâmetro de rota string.

**Correção:** declare o parâmetro como `string` e faça cast:

```php
public function show(string $id): Response
{
    $id = (int) $id;
}
```

### Mutações de Response Perdidas

**Sintoma:** um middleware define um header, mas a response final não tem.

**Causa:** o controller retorna uma **nova** Response em vez da injetada.

**Correção:**

```php
// ✅ Use a response injetada
public function index(Response $response): Response
{
    return $response->html('<h1>Olá</h1>');
}
```

---

## Veja Também

- [Rotas](routes.md) — registrando rotas com handlers
- [Grupos](groups.md) — controllers agrupados
- [Middleware](middleware.md) — pipeline before/after
- [Request](request.md) — todos os métodos de request
- [Response](response.md) — todos os builders de response
- [Container](container.md) — DI e autowiring

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/controllers.md)