Rotas
← Voltar ao README | 🇺🇸 English

As rotas são o núcleo do Router. Elas associam um método HTTP + caminho a um handler (closure ou controller).

Índice
Registrando Rotas

Métodos HTTP

Parâmetros Dinâmicos

Parâmetros Wildcard

Rotas Nomeadas

Atributos da Rota

Tipos de Handler

Referência de Sintaxe de Parâmetros

Normalização de Caminho

Correspondência de Rotas

Erros Comuns

Registrando Rotas
Cada verbo HTTP tem seu próprio método no Router:

php
$router->get($path, $handler, $name = null, $middleware = null);
$router->post($path, $handler, $name = null, $middleware = null);
$router->put($path, $handler, $name = null, $middleware = null);
$router->patch($path, $handler, $name = null, $middleware = null);
$router->delete($path, $handler, $name = null, $middleware = null);
Todos os métodos retornam $this, então podem ser encadeados:

php
$router
    ->get('/about', [SiteController::class, 'about'], name: 'site.about')
    ->post('/contact', [ContactController::class, 'store'], name: 'contact.store')
    ->get('/health', fn($req, $res) => $res->json(['status' => 'ok']));
Assinatura
Parâmetro	Tipo	Obrigatório	Descrição
$path	string	✅	O caminho da rota (ex.: /user/{id})
$handler	callable|array	✅	Closure ou [Controller::class, 'method']
$name	?string	❌	Nome da rota para geração de URL
$middleware	array|string|null	❌	Middleware específico da rota
Métodos HTTP
Método	Propósito	Exemplo
GET	Recuperar dados	$router->get('/user/{id}', [UserController::class, 'show']);
POST	Criar dados	$router->post('/user', [UserController::class, 'store']);
PUT	Substituir dados	$router->put('/user/{id}', [UserController::class, 'replace']);
PATCH	Atualizar dados	$router->patch('/user/{id}', [UserController::class, 'update']);
DELETE	Remover dados	$router->delete('/user/{id}', [UserController::class, 'destroy']);
Método Não Permitido (405)
Se uma rota existe para GET /user mas a requisição é POST /user, o Router retorna 405 Method Not Allowed (não 404).

Parâmetros Dinâmicos
Use {param} para capturar um único segmento de URL (não inclui /).

Exemplo Básico
php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
Requisição: GET /user/42

Resolvido: $id = "42"

Controller:

php
class UserController
{
    public function show(string $id): Response
    {
        return (new Response())->text("User ID: {$id}");
    }
}
Múltiplos Parâmetros
php
$router->get('/post/{slug}/comment/{id}', [CommentController::class, 'show']);
Requisição: GET /post/hello-world/comment/7

Resolvido: $slug = "hello-world", $id = "7"

Parâmetros Não Correspondem a /
php
$router->get('/user/{id}', ...);
URL	Corresponde?	Por quê
/user/42	✅	42 é um único segmento
/user/42/profile	❌	Dois segmentos
/user/	❌	Segmento vazio
/user	❌	Sem segmento
Parâmetros Wildcard
Use {param*} para capturar um ou mais segmentos (incluindo /).

Exemplo Básico
php
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');
Requisição: GET /files/docs/2025/relatorio.pdf

Resolvido: $path = ['docs', '2025', 'relatorio.pdf']

Controller:

php
class FileController
{
    public function download(array $path): Response
    {
        $file = implode('/', $path);  // "docs/2025/relatorio.pdf"
        return (new Response())->text("Download: {$file}");
    }
}
Nota: parâmetros wildcard são sempre retornados como arrays de segmentos.

Combinado com Parâmetros Regulares
php
$router->get('/user/{id}/files/{path*}', [FileController::class, 'userFile']);
Requisição: GET /user/42/files/docs/relatorio.pdf

Resolvido: $id = "42", $path = ['docs', 'relatorio.pdf']

Rotas Nomeadas
Dê à rota um nome para referenciá-la em outros lugares (geração de URL, redirecionamentos, helpers de view).

php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
Regras de Nomenclatura
Use notação de ponto para hierarquia: user.show, admin.users.index

Deve ser único em toda a aplicação

Case-sensitive

Pode conter letras, números, ., _, -

Acessando o Nome
php
$route = $request->route();
echo $route->getName();  // "user.show"
Usando o Nome
php
$url = $router->urlFor('user.show', ['id' => 42]);
// → https://meusite.com/user/42
📖 Documentação completa de geração de URL →

Nomeação em Grupos
Quando uma rota está dentro de um grupo com name, o nome do grupo é prefixado:

php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    // nome final: admin.users.index
});
Atributos da Rota
Cada Route suporta atributos arbitrários para metadados.

Definindo Atributos
php
$route->setAttribute('context', 'admin');
$route->setAttribute('version', 'v2');
$route->setAttribute('requires_auth', true);
Lendo Atributos
php
$route->getAttribute('context');           // "admin"
$route->getAttribute('missing', 'default'); // "default"
$route->hasAttribute('context');           // true
$route->getAttributes();                   // todos
Atributos Automáticos
O Router define um atributo automaticamente:

Atributo	Definido por	Valores
context	Router::addRoute()	admin, ecommerce, ou custom via group(context: ...)
Usando Atributos
Caso de uso comum — escolher uma view de erro com base no contexto:

php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
📖 Documentação completa de tratamento de erros →

Tipos de Handler
Closures
php
$router->get('/health', function ($request, $response, $params) {
    return $response->json(['status' => 'ok']);
});
Argumentos: Request $request, Response $response, array $params

Métodos de Classe
php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
O Router resolve o controller via container (ou autowiring) e injeta parâmetros por tipo e por nome.

Métodos Estáticos
php
$router->get('/ping', [HealthController::class, 'ping']);
Comparação de Assinaturas
Tipo de handler	Exemplo	Suporte a DI
Closure	fn($req, $res) => ...	❌ (apenas $req, $res, $params)
Método de classe	[UserController::class, 'show']	✅ (DI completa)
Referência de Sintaxe de Parâmetros
Sintaxe	Nome	Captura	Exemplo
{id}	Nomeado	Um segmento (sem /)	/user/{id} → /user/42 → $id = "42"
{path*}	Wildcard	Um ou mais segmentos	/files/{path*} → /files/a/b → $path = ['a','b']
{id:\d+}	(não suportado)	—	—
{id?}	(não suportado)	—	—
Suportado: nomeado {} e wildcard {*}.
Não suportado: restrições regex, parâmetros opcionais (use valores default no controller).

Normalização de Caminho
O Router normaliza caminhos durante o registro:

Entrada	Normalizado
/users	/users
users	/users
/users/	/users
''	/
'/'	/
Aplicação de Prefixo de Grupo
php
$router->group('/admin', callback: function ($r) {
    $r->get('users', ...);       // → /admin/users
    $r->get('/users', ...);      // → /admin/users
    $r->get('/', ...);           // → /admin
    $r->get('', ...);            // → /admin
});
Correspondência de Rotas
O Router faz match de requisições nesta ordem:

Rotas estáticas — lookup direto por caminho (O(1))

Rotas dinâmicas — match por regex (itera)

Estática vs Dinâmica
Caminho	Tipo	Correspondido por
/users	Estática	Hash lookup
/users/{id}	Dinâmica	Regex
Rotas estáticas sempre vencem quando ambas poderiam corresponder.

Exemplo
php
$router->get('/users/list', [UserController::class, 'list']);   // estática
$router->get('/users/{id}', [UserController::class, 'show']);   // dinâmica
Requisição	Correspondido por
GET /users/list	list (estática)
GET /users/42	show (dinâmica)
Ordem Importa para Rotas Dinâmicas
Rotas dinâmicas são correspondidas na ordem de registro. Registre rotas específicas antes das genéricas:

php
// ✅ Ordem correta
$router->get('/users/new', [UserController::class, 'create']);
$router->get('/users/{id}', [UserController::class, 'show']);

// ❌ Ordem errada — /users/new corresponderia a {id}
$router->get('/users/{id}', [UserController::class, 'show']);
$router->get('/users/new', [UserController::class, 'create']);
Erros Comuns
404 Not Found
A rota não existe:

php
$router->get('/users', ...);
// Requisição: GET /user → 404 (typo: falta 's')
405 Method Not Allowed
O caminho existe, mas o método não:

php
$router->get('/users', ...);
// Requisição: POST /users → 405
Handler Inválido
php
$router->get('/x', 'not-a-callable');
// ❌ InvalidArgumentException: Handler inválido
Handlers válidos:

Closure

[ClassName::class, 'method']

Controller Inválido
php
$router->get('/x', [NonExistentClass::class, 'method']);
// ❌ InvalidArgumentException: Controller inválido
A classe deve existir no momento do registro.

Rotas Duplicadas
Se você registrar o mesmo caminho + método duas vezes, a primeira vence:

php
$router->get('/x', fn() => 'first');
$router->get('/x', fn() => 'second');
// GET /x → "first"
O Router não avisa sobre duplicatas.

Exemplos
API RESTful
php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');
$router->get('/posts/{id}', [PostController::class, 'show'], name: 'posts.show');
$router->post('/posts', [PostController::class, 'store'], name: 'posts.store');
$router->put('/posts/{id}', [PostController::class, 'update'], name: 'posts.update');
$router->delete('/posts/{id}', [PostController::class, 'destroy'], name: 'posts.destroy');
Download de Arquivo
php
$router->get('/storage/{path*}', [StorageController::class, 'serve'], name: 'storage.serve');
// GET /storage/uploads/2025/avatar.png
// → $path = ['uploads', '2025', 'avatar.png']
Multi-Tenant
php
$router->get('/{tenant}/dashboard', [DashboardController::class, 'index']);
// GET /acme/dashboard → $tenant = "acme"
// GET /contoso/dashboard → $tenant = "contoso"
Health Check (Closure)
php
$router->get('/health', fn($req, $res) => $res->json([
    'status' => 'ok',
    'time'   => time(),
]));
Veja Também
Grupos — agrupar rotas com prefixo, nome, contexto

Middleware — pipeline before/after

Controllers — closures e classes

Geração de URL — urlFor()

Tratamento de Erros — 404, 405, handlers customizados

← Voltar ao README | 🇺🇸 English