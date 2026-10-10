# Cache de Rotas

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/cache.md)

O Router pode **compilar rotas uma vez** e carregá-las de um **arquivo PHP** em cada requisição subsequente. Isso reduz drasticamente o uso de CPU, evitando recompilação de regex e re-registro de rotas.

---

## Índice

- [Visão Geral](#visão-geral)
- [Quando Usar](#quando-usar)
- [Como Funciona](#como-funciona)
- [Configuração](#configuração)
- [Métodos de Cache](#métodos-de-cache)
- [O Que É Cacheado](#o-que-é-cacheado)
- [O Que NÃO É Cacheado](#o-que-não-é-cacheado)
- [Invalidando o Cache](#invalidando-o-cache)
- [Formato do Arquivo de Cache](#formato-do-arquivo-de-cache)
- [Padrões Comuns](#padrões-comuns)
- [Erros Comuns](#erros-comuns)

---

## Visão Geral

A compilação de rotas (transformar `/user/{id}` em uma regex) é **barata**, mas a cada requisição isso se acumula. O cache:

1. Compila todas as rotas uma vez
2. Serializa para um arquivo PHP
3. Nas requisições seguintes, carrega o arquivo diretamente

### Impacto de Performance

| Configuração | Rotas | Tempo (aprox.) |
|--------------|-------|----------------|
| Sem cache, 100 rotas | 100 | ~5-15 ms |
| Com cache, 100 rotas | 100 | ~0.5-2 ms |

**Números reais variam**, mas o cache tipicamente economiza **70-90%** do overhead de correspondência de rotas em apps grandes.

### O Que "Compilar" Significa

Para cada rota dinâmica (`/user/{id}`), o Router constrói uma **regex**:

```
/user/{id}  →  ~^/user/([^/]+)$~
```

Isso é feito uma vez por rota. O cache armazena a regex compilada, então a próxima requisição não precisa reconstruí-la.

---

## Quando Usar

### Use o cache quando:

- ✅ Você tem **muitas rotas** (>50)
- ✅ Sua app roda em **produção**
- ✅ Rotas **mudam raramente**
- ✅ Você quer **performance máxima**

### Pule o cache quando:

- ❌ Você está **desenvolvendo** (rotas mudam constantemente)
- ❌ Você tem **poucas rotas** (<20)
- ❌ Você faz **deploy frequente** (passo extra)

**Recomendação:** habilite em **produção**, desabilite em **desenvolvimento**.

---

## Como Funciona

### Fluxo Sem Cache

```
Requisição
  ↓
Dispatcher criado
  ↓
compile()       ← parseia /user/{id} → regex
  ↓
dispatch()
```

### Fluxo Com Cache

```
Requisição
  ↓
Dispatcher criado
  ↓
loadCache()     ← carrega rotas pré-compiladas do arquivo
  ↓
dispatch()
```

Se o arquivo de cache **não existe**, o Router cai em `compile()` e escreve o cache.

---

## Configuração

### Configuração Básica

```php
$router = new Router('https://meusite.com');

$router->enableCache(__DIR__ . '/cache/routes.php');

if (!$router->loadCache()) {
    // Cache miss → registre rotas
    $router->get('/', [HomeController::class, 'index'], name: 'home');
    $router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
    // ... mais rotas ...

    // Compila e salva
    $router->compile();
    $router->cacheCompiledRoutes();
}

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
```

### Com `index.php`

```php
<?php

declare(strict_types=1);

ob_start();

use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://meusite.com');
$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (!$router->loadCache()) {
    // Registre todas as rotas
    require __DIR__ . '/../routes/web.php';

    $router->compile();
    $router->cacheCompiledRoutes();
}

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();

while (ob_get_level() > 0) {
    ob_end_flush();
}
```

---

## Métodos de Cache

### `enableCache(string $cacheFile): self`

Registra o caminho do arquivo de cache e habilita o cache.

```php
$router->enableCache('/caminho/para/routes.php');
```

O arquivo **não precisa existir ainda** — o Router o cria na primeira chamada de `cacheCompiledRoutes()`.

### `loadCache(): bool`

Tenta carregar as rotas compiladas do arquivo de cache.

| Retorno | Significado |
|---------|-------------|
| `true` | Cache carregado com sucesso — rotas prontas |
| `false` | Cache não habilitado, arquivo ausente ou inválido |

```php
if (!$router->loadCache()) {
    // registre rotas
}
```

### `compile(): void`

Compila rotas em estruturas de lookup (hash estático + lista de regex dinâmicas).

**Idempotente:** chamar `compile()` duas vezes não faz nada na segunda vez (se já compilado).

### `cacheCompiledRoutes(): void`

Escreve as rotas compiladas no arquivo de cache.

```php
$router->compile();
$router->cacheCompiledRoutes();
```

**Nota:** `compile()` é chamado automaticamente por `cacheCompiledRoutes()` se as rotas ainda não foram compiladas.

### Resumo dos Métodos

| Método | Propósito |
|--------|-----------|
| `enableCache($file)` | Habilita o cache |
| `loadCache()` | Carrega do cache |
| `compile()` | Compila rotas |
| `cacheCompiledRoutes()` | Escreve o cache |

---

## O Que É Cacheado

O arquivo de cache contém **todas as rotas** com:

| Campo | Cacheado? | Exemplo |
|-------|-----------|---------|
| `method` | ✅ | `GET` |
| `path` | ✅ | `/user/{id}` |
| `handler` | ✅ | `UserController::class` (string) |
| `action` | ✅ | `show` |
| `name` | ✅ | `user.show` |
| `middlewares` | ✅ | `[AuthMiddleware::class]` |
| `regex` | ✅ | `~^/user/([^/]+)$~` |
| `paramNames` | ✅ | `['id']` |
| `attributes` | ✅ | `['context' => 'admin']` |

### Índice de Nomes de Rota

O `nameIndex` também é cacheado:

```php
[
    'user.show' => ['method' => 'GET', 'path' => '/user/{id}'],
    // ...
]
```

Usado por `urlFor()`.

### Atributos Customizados

Quaisquer atributos que você definir numa rota via `setAttribute()` são preservados entre carregamentos do cache.

```php
$route->setAttribute('context', 'admin');
$route->setAttribute('version', 'v2');
// Ambos sobrevivem ao ciclo cache → load → dispatch
```

---

## O Que NÃO É Cacheado

### 1. Handlers de Closure

Rotas com **handlers de closure** são **ignoradas** no cache.

```php
$router->get('/health', function ($req, $res) {
    return $res->json(['status' => 'ok']);
});
// ❌ Não cacheada — closures não podem ser serializadas
```

**Por quê:** `var_export()` não consegue serializar objetos `Closure`.

**Impacto:** a cada requisição, rotas com closure precisam ser registradas **mesmo com o cache ativo**.

### 2. Serviços

O cache **não** armazena serviços do container. Apenas rotas.

### 3. Config

O `projectUrl` **não** é cacheado. É definido em `new Router(...)`.

### Resumo

| Item | Cacheado? |
|------|-----------|
| Rotas estáticas | ✅ |
| Rotas dinâmicas | ✅ |
| Índice de nomes | ✅ |
| Atributos | ✅ |
| Handlers de closure | ❌ |
| Serviços do container | ❌ |
| Config | ❌ |

---

## Invalidando o Cache

O cache **não se auto-invalida**. Você deve deletar ou regenerar quando:

- Adicionar uma rota nova
- Remover uma rota
- Mudar o caminho, nome ou handler de uma rota
- Mudar um middleware numa rota

### Exclusão Manual

```bash
rm storage/cache/routes.php
```

A próxima requisição dispara `loadCache()` → retorna `false` → recompila.

### Regerar no Deploy

No seu script de deploy:

```bash
# Após puxar código novo
rm -f storage/cache/routes.php
```

Ou:

```bash
php bin/compile-routes.php
```

Onde `bin/compile-routes.php` registra todas as rotas e chama `cacheCompiledRoutes()`.

### Workflow de Dev

Durante o desenvolvimento, **desabilite o cache**:

```php
$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (getenv('APP_ENV') !== 'production' || !$router->loadCache()) {
    // registre rotas
    $router->compile();

    if (getenv('APP_ENV') === 'production') {
        $router->cacheCompiledRoutes();
    }
}
```

Assim:

- **Dev:** sempre recompila (sem cache obsoleto)
- **Prod:** carrega do cache

---

## Formato do Arquivo de Cache

O arquivo de cache é um arquivo PHP que retorna um array estruturado:

```php
<?php

return [
    'static' => [
        'GET' => [
            '/' => [
                'method' => 'GET',
                'path' => '/',
                'handler' => 'App\\Controller\\HomeController',
                'action' => 'index',
                'name' => 'home',
                'middlewares' => [],
                'regex' => null,
                'paramNames' => [],
                'attributes' => ['context' => 'ecommerce'],
            ],
            '/about' => [ /* ... */ ],
        ],
        'POST' => [ /* ... */ ],
    ],
    'dynamic' => [
        'GET' => [
            [
                'regex' => '~^/user/([^/]+)$~',
                'route' => [ /* ... */ ],
                'paramNames' => ['id'],
            ],
            // ...
        ],
    ],
    'names' => [
        'home' => ['method' => 'GET', 'path' => '/'],
        'user.show' => ['method' => 'GET', 'path' => '/user/{id}'],
        // ...
    ],
];
```

### Estrutura

| Chave | Conteúdo |
|-------|----------|
| `static` | Rotas estáticas agrupadas por método HTTP |
| `dynamic` | Rotas dinâmicas (com regex) agrupadas por método HTTP |
| `names` | Índice de nomes para `urlFor()` |

### Por Que Este Formato?

- **PHP puro** — carregamento mais rápido possível (amigável ao OPcache)
- **Sem `unserialize()`** — seguro contra injeção de objetos
- **Legível** — você pode inspecioná-lo

---

## Padrões Comuns

### Desenvolvimento vs Produção

```php
$isProd = ($_ENV['APP_ENV'] ?? 'development') === 'production';

$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (!$isProd || !$router->loadCache()) {
    require __DIR__ . '/../routes/web.php';

    $router->compile();

    if ($isProd) {
        $router->cacheCompiledRoutes();
    }
}
```

### Cache Por Ambiente

Arquivos diferentes para ambientes diferentes:

```php
$cacheFile = __DIR__ . "/../storage/cache/routes.{$_ENV['APP_ENV']}.php";
$router->enableCache($cacheFile);
```

Evita misturar rotas de dev e prod.

### Pré-compilar no Deploy

Crie `bin/compile-routes.php`:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://meusite.com');
require __DIR__ . '/../routes/web.php';

$router->compile();
$router->cacheCompiledRoutes();

echo "Rotas compiladas para storage/cache/routes.php\n";
```

Rode no deploy:

```bash
php bin/compile-routes.php
```

### Aquecimento de Cache

No seu pipeline de deploy:

```bash
# 1. Puxa código
git pull

# 2. Instala dependências
composer install --no-dev --optimize-autoloader

# 3. Compila rotas
php bin/compile-routes.php

# 4. Reinicia PHP-FPM (opcional, para OPcache)
sudo systemctl reload php8.1-fpm
```

### Notas de Permissão

O arquivo de cache precisa de **permissão de escrita** durante a compilação:

```bash
chmod -R 775 storage/cache
```

Se você roda PHP-FPM como `www-data`:

```bash
chown -R www-data:www-data storage/cache
```

---

## Erros Comuns

### Cache Não Está Sendo Escrito

**Sintoma:** `routes.php` não aparece em `storage/cache/`.

**Causas:**

1. **Diretório não existe** — o Router o cria com `mkdir($dir, 0777, true)`, mas permissões podem bloquear
2. **Permissão negada** — o processo PHP não pode escrever
3. **`cacheCompiledRoutes()` não é chamado** — verifique seu código

**Correção:**

```bash
mkdir -p storage/cache
chmod -R 775 storage/cache
```

### Cache Obsoleto Após Adicionar Rotas

**Sintoma:** rotas novas retornam 404 após deploy.

**Causa:** o arquivo de cache não foi regenerado.

**Correção:**

```bash
rm storage/cache/routes.php
```

Ou rode `php bin/compile-routes.php`.

### Rotas de Closure Faltando Após Cache

**Sintoma:** rotas definidas com closures funcionam em dev mas não em prod.

**Causa:** closures não são cacheadas. Precisam ser registradas **a cada requisição**, mesmo com o cache ativo.

**Correção:** registre closures **fora** do bloco `if (!$router->loadCache())`:

```php
// Sempre registre closures
$router->get('/health', fn($req, $res) => $res->json(['ok' => true]));

// Registre rotas cacheáveis apenas em cache miss
if (!$router->loadCache()) {
    $router->get('/user/{id}', [UserController::class, 'show']);
    $router->compile();
    $router->cacheCompiledRoutes();
}
```

### Cache Carregado Mas Rotas Não Correspondem

**Sintoma:** `loadCache()` retorna `true`, mas requisições dão 404.

**Causa:** você mudou o `projectUrl` e os caminhos compilados não correspondem.

**Correção:** delete e recompile.

### Arquivo de Cache Inválido

**Sintoma:** `ParseError` ou falha no `require` de `routes.php`.

**Causa:** o arquivo está corrompido (escrita parcial, edição manual).

**Correção:** delete e recompile.

### `urlFor()` Falha para uma Rota Nomeada

**Sintoma:** `InvalidArgumentException: Named route 'x' not found`.

**Causa:** a rota foi adicionada mas o cache não foi regenerado.

**Correção:** limpe o cache.

---

## Veja Também

- [Rotas](routes.md) — registro de rotas
- [Grupos](groups.md) — rotas agrupadas (cacheadas como rotas normais)
- [Geração de URL](url-generation.md) — `urlFor()` usa o índice de nomes

---

[← Voltar ao README](../../README.pt.md) | [🇺🇸 English](../en/cache.md)