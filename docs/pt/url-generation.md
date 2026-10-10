# Geração de URL

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/url-generation.md)

Gere URLs a partir de **rotas nomeadas** usando `urlFor()`. Isso desacopla sua aplicação de caminhos hardcoded — mude uma rota num só lugar, e todos os links se atualizam.

---

## Índice

- [Visão Geral](#visão-geral)
- [Assinatura](#assinatura)
- [Uso Básico](#uso-básico)
- [Parâmetros de Rota](#parâmetros-de-rota)
- [Wildcards](#wildcards)
- [Query Strings](#query-strings)
- [Rotas Nomeadas em Grupos](#rotas-nomeadas-em-grupos)
- [Parâmetros Ausentes](#parâmetros-ausentes)
- [Erros](#erros)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Visão Geral

`urlFor()` constrói uma URL a partir de uma rota nomeada e um array de parâmetros.

### Por Que Usar

**Sem `urlFor()`:**

```php
<a href="/user/<?= $user->id ?>">Perfil</a>
```

**Problemas:**

- Caminho hardcoded
- Se `/user/{id}` virar `/profile/{id}`, você precisa atualizar cada link
- Sem URL encoding
- Sem tratamento de query string

**Com `urlFor()`:**

```php
<a href="<?= $router->urlFor('user.show', ['id' => $user->id]) ?>">Perfil</a>
```

**Benefícios:**

- Fonte única de verdade
- URL encoding automático
- Query strings tratadas
- Nomes de rota navegáveis pela IDE

---

## Assinatura

```php
public function urlFor(
    string $name,
    array $params = [],
    array $query = []
): string;
```

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `$name` | `string` | ✅ | Nome da rota |
| `$params` | `array` | ❌ | Parâmetros de caminho |
| `$query` | `array` | ❌ | Parâmetros de query string |

**Retorna:** a URL completa, prefixada com o `projectUrl`.

---

## Uso Básico

```php
$router->get('/about', [SiteController::class, 'about'], name: 'about');

$url = $router->urlFor('about');
// → https://meusite.com/about
```

**Nota:** o `projectUrl` (passado para `new Router(...)`) é prependado.

```php
$router = new Router('https://meusite.com');
$router->urlFor('about');  // https://meusite.com/about
```

Se você passou `'https://meusite.com/app'`:

```php
$router = new Router('https://meusite.com/app');
$router->urlFor('about');  // https://meusite.com/app/about
```

---

## Parâmetros de Rota

Passe parâmetros como um array associativo:

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$router->urlFor('user.show', ['id' => 42]);
// → https://meusite.com/user/42
```

### Múltiplos Parâmetros

```php
$router->get('/post/{slug}/comment/{id}', [CommentController::class, 'show'], name: 'comment.show');

$router->urlFor('comment.show', [
    'slug' => 'hello-world',
    'id'   => 7,
]);
// → https://meusite.com/post/hello-world/comment/7
```

### Ordem de Parâmetros Não Importa

```php
$router->urlFor('comment.show', ['id' => 7, 'slug' => 'hello-world']);
// → https://meusite.com/post/hello-world/comment/7
```

O Router substitui placeholders por **nome**, não por posição.

### URL Encoding

Valores **não** são URL-encoded automaticamente por `urlFor()`. Codifique manualmente se necessário:

```php
$router->urlFor('post.show', ['slug' => urlencode('hello world')]);
// → https://meusite.com/post/hello+world
```

---

## Wildcards

Parâmetros wildcard (`{path*}`) aceitam um **array**:

```php
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');

$router->urlFor('files.download', ['path' => ['docs', '2024', 'file.pdf']]);
// → https://meusite.com/files/docs/2024/file.pdf
```

### Valor String

Você pode passar uma string — é usada como está:

```php
$router->urlFor('files.download', ['path' => 'docs/2024/file.pdf']);
// → https://meusite.com/files/docs/2024/file.pdf
```

### Wildcard Vazio

Um array vazio produz nenhum segmento:

```php
$router->urlFor('files.download', ['path' => []]);
// → https://meusite.com/files/
```

---

## Query Strings

Passe um **terceiro argumento** para parâmetros de query:

```php
$router->urlFor('user.show', ['id' => 42], ['ref' => 'email']);
// → https://meusite.com/user/42?ref=email
```

### Múltiplos Parâmetros de Query

```php
$router->urlFor('users.index', [], ['page' => 2, 'sort' => 'name']);
// → https://meusite.com/users?page=2&sort=name
```

### Parâmetros de Query em Array

```php
$router->urlFor('users.index', [], ['tags' => ['php', 'router']]);
// → https://meusite.com/users?tags%5B0%5D=php&tags%5B1%5D=router
```

Usa `http_build_query()` do PHP, então arrays são codificados no formato PHP.

### Apenas Query, Sem Path

```php
$router->get('/', [HomeController::class, 'index'], name: 'home');

$router->urlFor('home', [], ['utm_source' => 'newsletter']);
// → https://meusite.com/?utm_source=newsletter
```

---

## Rotas Nomeadas em Grupos

Quando uma rota está dentro de um grupo com `name`, o prefixo é aplicado:

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});

$router->urlFor('admin.users.index');
// → https://meusite.com/admin/users
```

### Prefixos Aninhados

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
    });
});

$router->urlFor('admin.users.list');
// → https://meusite.com/admin/users/list
```

### Rotas Nomeadas com Parâmetros

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users/{id}', [UserController::class, 'show'], name: 'users.show');
});

$router->urlFor('admin.users.show', ['id' => 42]);
// → https://meusite.com/admin/users/42
```

---

## Parâmetros Ausentes

Se uma rota tem parâmetros e você **não** os passa, o placeholder é **removido**:

```php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');

$router->urlFor('user.show');
// → https://meusite.com/user/
```

**Comportamento:** o placeholder `{id}` é removido da URL.

**Nota:** isso geralmente **não é o que você quer** — sempre passe os parâmetros obrigatórios.

### O Que É Removido

O Router usa esta regex para remover placeholders não substituídos:

```
\{[a-zA-Z_][a-zA-Z0-9_-]*\*?\}
```

Então tanto `{id}` quanto `{path*}` são removidos se não substituídos.

---

## Erros

### Nome de Rota Desconhecido

```php
$router->urlFor('nonexistent');
// ❌ InvalidArgumentException: Named route 'nonexistent' not found.
```

**Causa:** o nome não existe no registro de rotas.

**Razões comuns:**

1. **Typo** — `users.show` vs `user.show`
2. **Rota não registrada** — a rota está atrás de um condicional
3. **Cache não regenerado** — após adicionar uma rota, limpe o cache

### Rota Não Está no `nameIndex`

Se uma rota **não tem nome** (você não passou `name:`), ela não está no índice:

```php
$router->get('/about', [SiteController::class, 'about']);  // sem nome

$router->urlFor('about');  // ❌ não encontrado
```

**Correção:** sempre dê nome às rotas quando planeja gerar URLs.

---

## Padrões Comuns

### Links RESTful de Recurso

```php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');
$router->get('/posts/{id}', [PostController::class, 'show'], name: 'posts.show');
$router->get('/posts/{id}/edit', [PostController::class, 'edit'], name: 'posts.edit');

// Numa view:
<a href="<?= $router->urlFor('posts.index') ?>">Todos os posts</a>
<a href="<?= $router->urlFor('posts.show', ['id' => $post->id]) ?>"><?= $post->title ?></a>
<a href="<?= $router->urlFor('posts.edit', ['id' => $post->id]) ?>">Editar</a>
```

### Paginação

```php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');

// Página 3
$url = $router->urlFor('posts.index', [], ['page' => 3]);
// → https://meusite.com/posts?page=3
```

### Redirect Após Ação

```php
public function store(Request $request, Response $response): Response
{
    $post = $this->posts->create($request->all());

    return $response->redirect(
        $this->router->urlFor('posts.show', ['id' => $post->id])
    );
}
```

### Links do Painel Admin

```php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});

// Numa view admin:
<a href="<?= $router->urlFor('admin.dashboard') ?>">Dashboard</a>
<a href="<?= $router->urlFor('admin.users.index') ?>">Usuários</a>
```

### Links em Emails

```php
$verifyUrl = $router->urlFor('auth.verify', ['token' => $token]);
// → https://meusite.com/auth/verify/abc123

mail($email, 'Verifique sua conta', "Clique: {$verifyUrl}");
```

### Absoluto vs Relativo

`urlFor()` sempre retorna uma URL **absoluta** (com scheme e host):

```php
$router->urlFor('about');
// → https://meusite.com/about
```

Se você precisa de uma URL **relativa**:

```php
$url = parse_url($router->urlFor('about'), PHP_URL_PATH);
// → /about
```

### Injetando o Router nas Views

Como `urlFor()` está no Router, passe-o para o seu engine de views:

```php
$this->view->render('page', [
    'router' => $router,
    'post'   => $post,
]);
```

```php
<a href="<?= $router->urlFor('posts.show', ['id' => $post->id]) ?>">
```

### Helper de Rota (em Template Engine)

Se você usa Plates, registre uma função:

```php
$engine->registerFunction('route', fn(string $name, array $params = [], array $query = []) =>
    $router->urlFor($name, $params, $query)
);
```

Então nos templates:

```html
<a href="<?= $this->route('posts.show', ['id' => $post->id]) ?>">Ver</a>
```

---

## Erros Comuns

### `InvalidArgumentException` em `urlFor()`

**Sintoma:** `Named route 'x' not found.`

**Causas:**

1. **Typo** no nome da rota
2. **Rota não registrada** (atrás de condicional que você não entrou)
3. **Cache não regenerado** após adicionar a rota

**Correção:**

```php
// Debug: liste todos os nomes registrados
var_dump(array_keys($router->getNameIndex()));
```

### URL Sem Query String

**Sintoma:** `urlFor('posts.index', [], ['page' => 2])` não adiciona `?page=2`.

**Causa:** passando params no argumento errado.

**Correção:** o **terceiro** argumento é para query string:

```php
// ✅ Correto
$router->urlFor('posts.index', [], ['page' => 2]);

// ❌ Errado — page vira parâmetro de path
$router->urlFor('posts.index', ['page' => 2]);
```

### Host ou Scheme Errado

**Sintoma:** `urlFor()` retorna uma URL com host errado.

**Causa:** `projectUrl` foi definido incorretamente ao criar o Router.

**Correção:**

```php
$router = new Router('https://meusite.com');  // correto
$router = new Router('https://staging.meusite.com');  // staging
```

**Dica:** carregue a URL do env:

```php
$router = new Router($_ENV['APP_URL']);
```

### Placeholder de Path Deixado na URL

**Sintoma:** `https://meusite.com/user/{id}` (literal `{id}`).

**Causa:** o placeholder tem um caractere não permitido pela regex.

**Padrão de placeholder suportado:** `[a-zA-Z_][a-zA-Z0-9_-]*`

**Correção:** renomeie o placeholder para usar apenas letras, números, `_`, `-`.

### Array de Wildcard Não Expandido

**Sintoma:** `urlFor('files', ['path' => ['a', 'b']])` produz `Array`.

**Causa:** passando o array num contexto de string.

**Correção:** `urlFor()` lida com arrays para wildcards automaticamente. Verifique a rota:

```php
$router->get('/files/{path*}', ..., name: 'files');
// ✅ Wildcard
```

vs

```php
$router->get('/files/{path}', ..., name: 'files');
// ❌ Segmento único — passar array vira "Array"
```

### Cache Não Regenerado

**Sintoma:** uma rota recém-nomeada não é encontrada.

**Causa:** o índice de nomes está em cache. Após adicionar rotas, limpe o cache.

**Correção:**

```bash
rm storage/cache/routes.php
```

📖 [Docs de cache →](cache.md)

---

## Veja Também

- [Rotas](routes.md) — nomeando rotas
- [Grupos](groups.md) — prefixos de nome
- [Cache](cache.md) — o índice de nomes e o cache
- [Response](response.md) — `redirect()` com `urlFor()`

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/url-generation.md)