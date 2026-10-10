# Response

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/response.md)

O objeto `Response` representa a resposta HTTP. Você **constrói** no controller (API fluente) e **retorna** — o `Dispatcher` **envia**.

---

## Índice

- [Visão Geral](#visão-geral)
- [Builders](#builders)
- [Headers & Status](#headers--status)
- [Getters](#getters)
- [Envio](#envio)
- [Referência Completa de Métodos](#referência-completa-de-métodos)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Visão Geral

O `Response` é criado pelo `Dispatcher` no início de cada requisição e passado para:

- **Middleware** (`before` / `after`)
- **Controllers** (via type-hint `Response $response`)
- **Closures** (argumento `$response`)

### Classe

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

**Nota:** você quase nunca instancia `Response` sozinho — o `Dispatcher` faz isso. Mas você **pode** criar uma se precisar de uma response nova:

```php
return (new Response())->json(['ok' => true]);
```

### Mutabilidade

Diferente do PSR-7, o `Response` é **mutável**. Todos os builders retornam `$this`, permitindo encadeamento.

### Envio Idempotente

`send()` usa um guard `$sent`: chamar duas vezes não tem efeito.

---

## Builders

Métodos builder definem tanto o status quanto o body numa única chamada.

### `html(string $html, int $status = 200): self`

Define `Content-Type: text/html; charset=utf-8` e o body.

```php
return $response->html('<h1>Olá</h1>');

return $response->html('<h1>Não encontrado</h1>', 404);
```

### `json(array $data, int $status = 200): self`

Define `Content-Type: application/json; charset=utf-8` e o body codificado em JSON.

```php
return $response->json(['ok' => true]);

return $response->json(['error' => 'Não encontrado'], 404);
```

**Opções de encoding:**

- `JSON_UNESCAPED_UNICODE` — mantém `ç`, `ã`, `é`, etc.
- `JSON_UNESCAPED_SLASHES` — mantém `/` sem escape

Então `{"url":"https://meusite.com/path"}` — não `{"url":"https:\/\/meusite.com\/path"}`.

### `text(string $text, int $status = 200): self`

Define `Content-Type: text/plain; charset=utf-8` e o body.

```php
return $response->text('pong');
```

### `redirect(string $url, int $status = 302): self`

Define o status e um header `Location`. **Sem body.**

```php
return $response->redirect('/login');

return $response->redirect('/login', 301);  // permanente
```

### `back(int $status = 302): self`

Redireciona para o header `HTTP_REFERER`.

```php
return $response->back();  // volta para a página anterior
```

**Segurança:** se o host do referer difere do host atual, redireciona para `/` (proteção contra open redirect).

---

## Headers & Status

### `status(int $code): self`

Define o código de status HTTP.

```php
$response->status(201);
```

### `header(string $name, string $value): self`

Define (ou substitui) um header.

```php
$response->header('X-Request-Id', 'abc123');
$response->header('Cache-Control', 'no-store');
```

### Encadeamento

Tudo retorna `$this`:

```php
return $response
    ->status(201)
    ->header('X-App', 'SulCompany')
    ->json(['ok' => true]);
```

---

## Getters

### `getStatusCode(): int`

Retorna o código de status atual.

```php
$code = $response->getStatusCode();  // 200
```

### `getBody(): mixed`

Retorna o body atual (string ou `null`).

```php
$body = $response->getBody();  // "<h1>Olá</h1>"
```

### `getHeaders(): array`

Retorna todos os headers.

```php
$headers = $response->getHeaders();
// → ['Content-Type' => '...', 'X-App' => '...']
```

### `hasContent(): bool`

Retorna `true` se o body não é `null` **ou** se há algum header.

```php
$response->hasContent();  // true se body != null OU headers não vazios
```

**Caso de uso:** verificar se um middleware definiu uma response antes de sobrescrever.

---

## Envio

### `send(): void`

Envia a response para o cliente.

**O que faz:**

1. Guard: não faz nada se já enviada
2. Chama `http_response_code($this->status)`
3. Envia cada header via `header()`
4. Ecoa o body (se não `null`)
5. Marca `$sent = true`

```php
$response->send();
```

### Quando Chamar

**Normalmente você não chama.** O `Dispatcher` chama `send()` no final da requisição.

**Você chama** no `index.php` ao tratar erros:

```php
$errorHandler->handle($status, $request, $response)->send();
```

### Output Buffering

`send()` faz `echo $body`. Se você tem output buffering ativo (`ob_start()`), o body vai para o buffer — você precisa descarregá-lo:

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

📖 [Notas importantes no README →](../../README.pt.md#-notas-importantes)

---

## Referência Completa de Métodos

| Método | Retorno | Descrição |
|--------|---------|-----------|
| `status($code)` | `self` | Define status HTTP |
| `header($name, $value)` | `self` | Define um header |
| `html($html, $status)` | `self` | Response HTML |
| `json($data, $status)` | `self` | Response JSON |
| `text($text, $status)` | `self` | Response texto puro |
| `redirect($url, $status)` | `self` | Redirect |
| `back($status)` | `self` | Redirect para referer |
| `getStatusCode()` | `int` | Status atual |
| `getBody()` | `mixed` | Body atual |
| `getHeaders()` | `array` | Todos os headers |
| `hasContent()` | `bool` | Body ou headers presentes |
| `send()` | `void` | Envia a response |

---

## Padrões Comuns

### Resposta de API JSON

```php
public function index(Response $response): Response
{
    $users = $this->users->all();
    return $response->json(['data' => $users]);
}
```

### Resposta Condicional (HTML vs JSON)

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

### 404 com Mensagem Customizada

```php
public function show(string $id, Response $response): Response
{
    $user = $this->users->find($id);

    if ($user === null) {
        return $response->json(['error' => 'Usuário não encontrado'], 404);
    }

    return $response->json($user);
}
```

### Redirect Após POST

```php
public function store(Request $request, Response $response): Response
{
    $post = $this->posts->create($request->post());
    return $response->redirect('/posts/' . $post->id);
}
```

### Voltar com Query

```php
return $response->redirect('/users?' . http_build_query(['page' => 2]));
```

### Headers Customizados

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
    return $response->status(204);  // sem body
}
```

### Download com Content-Disposition

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

## Erros Comuns

### Response Não Enviada

**Sintoma:** um controller retorna uma Response, mas a página fica em branco.

**Causa:** o `Dispatcher` não chamou `send()`, ou o buffer não foi descarregado.

**Correção (nível de app):**

```php
$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
```

O `Dispatcher` chama `send()` automaticamente.

**Correção (nível de buffer):**

```php
while (ob_get_level() > 0) {
    ob_end_flush();
}
```

### `Content-Length: 0` Mesmo Com Body

**Sintoma:** a response tem body, mas o cliente recebe `content-length: 0`.

**Causa:** output buffering está ativo, e o buffer não está sendo descarregado.

**Correção:** o mesmo acima — descarregue todos os buffers no final do script.

### `json()` Retorna JSON Malformado

**Sintoma:** `$response->json(['key' => $invalidValue])` produz saída vazia.

**Causa:** `json_encode()` falhou silenciosamente (ex.: `INF`, `NAN`, UTF-8 inválido).

**Correção:** valide os dados antes de codificar, ou use `JSON_THROW_ON_ERROR`:

```php
try {
    return $response->json(['data' => $data]);
} catch (\JsonException $e) {
    return $response->json(['error' => 'Dados inválidos'], 500);
}
```

**Nota:** o `Response` base não lança — silenciosamente define o body como `false`. Se precisar detectar, verifique `getBody()`.

### Redirect Não Funciona

**Sintoma:** chamando `redirect()` mas o browser fica na mesma página.

**Causas:**

1. **Headers já enviados** — algo ecoou antes do `send()`. Verifique `ob_start()`.
2. **Status errado** — verifique `getStatusCode()`.
3. **Header Location faltando** — verifique `getHeaders()`.

**Correção:**

```php
return $response->redirect('/login');  // 302 por padrão
```

### Header de Middleware Perdido

**Sintoma:** um middleware adiciona um header, mas a response final não tem.

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

- [Request](request.md) — acessando a requisição HTTP
- [Controllers](controllers.md) — retornando responses de controllers
- [Middleware](middleware.md) — modificando responses em middleware
- [Tratamento de Erros](error-handling.md) — construindo responses de erro

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/response.md)