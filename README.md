# Router @SulCompany

Um **roteador simples, flexível e sem dependências**, com suporte a:

- Rotas REST (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
- Parâmetros nomeados (`/user/{id}`)
- Parâmetros múltiplos dinâmicos (`/produto/{params*}`)
- Middlewares (`/route`, `[Controller::class, 'method']`, `Example\Middlewares\AuthMiddleware`)
- Grupos de rotas com prefixos
- Cache de rotas para otimização de performance
- Rotas nomeadas e geração de URLs
- Acesso fácil a dados de request e parâmetros dentro de controllers

---

## 🚀 Instalação

```bash
composer require sulcompany/router


🔹 Estrutura Básica do Projeto

<?php
use SulCompany\Router\Router;
use SulCompany\Router\Dispatcher;

$router = new Router("https://www.dominio.com");

// Adicionar middlewares globais
$router->addGlobalMiddleware(App\Http\Middlewares\AuthMiddleware::class);

// Registrar rotas (simples ou grupos)
// ...

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();

if ($dispatcher->error()) {
    echo "Erro HTTP: " . $dispatcher->error();
}


⚡ Fluxo do Router

Inicializar Router

Adicionar middlewares globais

Habilitar cache de rotas (opcional)

Registrar rotas simples ou grupos

Criar Dispatcher

Executar dispatch

Acessar dados ou tratar erros ($dispatcher->data(), $dispatcher->params(), $dispatcher->error())


📋 Tabela de Métodos do Router

| Método                                        | Descrição                             | Exemplo                                                                                                                   |
| --------------------------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `get($uri, $handler, $name=null)`             | Registra rota GET                     | `$router->get("/about", [SiteController::class,'about'], 'site.about');`                                                  |
| `post($uri, $handler, $name=null)`            | Registra rota POST                    | `$router->post("/user/register", [UserController::class,'register'], 'user.register');`                                   |
| `put($uri, $handler, $name=null)`             | Registra rota PUT                     | `$router->put("/user/{id}/profile", [UserController::class,'updateProfile'], 'user.updateProfile');`                      |
| `patch($uri, $handler, $name=null)`           | Registra rota PATCH                   | `$router->patch("/user/{id}/photo", [UserController::class,'updatePhoto'], 'user.updatePhoto');`                          |
| `delete($uri, $handler, $name=null)`          | Registra rota DELETE                  | `$router->delete("/user/{id}", [UserController::class,'delete'], 'user.delete');`                                         |
| `group($prefix, $callback, $middleware=null)` | Agrupa rotas com prefixo e middleware | `$router->group('admin', fn($r)=>$r->get('/', [DashController::class,'index']), middleware: AuthAdminMiddleware::class);` |
| `addGlobalMiddleware($middleware)`            | Adiciona middlewares globais          | `$router->addGlobalMiddleware(AuthMiddleware::class);`                                                                    |
| `enableCache($file)`                          | Habilita cache de rotas               | `$router->enableCache(__DIR__.'/cache/routes.php');`                                                                      |
| `loadCache(): bool`                           | Carrega rotas do cache                | `$router->loadCache();`                                                                                                   |
| `routes(): array`                             | Retorna todas as rotas registradas    | `$router->routes();`                                                                                                      |
| `urlFor($name, $params=[], $query=[])`        | Gera URL para rota nomeada            | `$router->urlFor('user.show', ['id'=>42], ['ref'=>'email']);`
                                                            |



🗂 Tabela de Métodos do Dispatcher

| Método               | Descrição                                     | Exemplo                    |
| -------------------- | --------------------------------------------- | -------------------------- |
| `dispatch(): bool`   | Executa a rota correspondente à requisição    | `$dispatcher->dispatch();` |
| `error(): ?int`      | Retorna código HTTP do erro (404, 405, etc)   | `$dispatcher->error();`    |
| `data(): ?array`     | Retorna dados da requisição (GET, POST, JSON) | `$dispatcher->data();`     |
| `params(): ?array`   | Retorna parâmetros extraídos da rota          | `$dispatcher->params();`   |
| `current(): ?object` | Retorna informações da rota atual             | `$dispatcher->current();`  |



🏷 Named Routes & URL Generation

| Exemplo                                                                        | Resultado                                                |
| ------------------------------------------------------------------------------ | -------------------------------------------------------- |
| `$router->urlFor('user.show', ['id'=>42]);`                                    | `https://www.dominio.com/user/42`                        |
| `$router->urlFor('product.show', ['slug'=>'produto'], ['ref'=>'newsletter']);` | `https://www.dominio.com/product/produto?ref=newsletter` |


📁 Grupos de Rotas & Middleware

$router->group('admin', function($router){
    $router->get('/dashboard', [Admin\DashController::class,'index'], 'admin.dashboard');
    $router->get('/users', [Admin\UserController::class,'list'], 'admin.users.list');
}, middleware: [AuthAdminMiddleware::class]);

Prefixo automático aplicado: /admin/dashboard
Middlewares do grupo + globais aplicados em cada rota do grupo


📌 Parâmetros Múltiplos Dinâmicos
$router->get('/files/{path*}', [FileController::class,'download'], 'files.download');

// URL: /files/docs/2025/relatorio.pdf
// $dispatcher->params() => ['path' => ['docs','2025','relatorio.pdf']]


📝 Acesso a Dados e Parâmetros em Controllers

class UserController {
    public function show(array $data, array $params) {
        $id = $params['id'] ?? null;
        $email = $data['email'] ?? null;
        echo "User ID: $id, Email: $email";
    }
}

$data: GET, POST e JSON da requisição
$params: parâmetros da rota ({id}, {slug}, {params*})


⚡ Cache de Rotas
Otimiza performance evitando recompilar regex a cada requisição
Sempre que adicionar novas rotas, limpe ou regenere o cache

$router->enableCache(__DIR__.'/cache/routes.php');

if (!$router->loadCache()) {
    $router->get('/about', [SiteController::class,'about']);

    $router->compile();
    $router->cacheCompiledRoutes();
}


✅ Resumo Visual do Fluxo
[ Router ]
  ↓ addGlobalMiddleware
  ↓ enableCache (opcional)
  ↓ register routes (get, post, put, patch, delete)
  ↓ group routes (prefix + middleware)
  ↓ compile & cache (opcional)
  ↓
[ Dispatcher ]
  ↓ dispatch()
  ↓
[ Controller / Closure ]
  ↓
[ Response / Error Handling ]


Credits

Francisco Dulo (GitHub
)

Sul Company Lda (GitHub
)

Todos os colaboradores (Contributors
)






```
