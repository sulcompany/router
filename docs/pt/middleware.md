Middleware
← Voltar ao README | 🇺🇸 English

Middleware permite executar lógica antes e/ou depois do controller. Usos comuns: autenticação, autorização, logging, restauração de sessão, proteção CSRF, modificação de requisição.

Índice
Estrutura

before()

after()

Registrando Middleware

Tipos de Middleware

Ordem de Execução

Interrompendo a Pipeline

Modificando a Response

Injeção de Dependências

Padrões Comuns

Erros Comuns

Estrutura
Um middleware é uma classe com um método opcional before() e/ou after().

php
namespace App\Http\Middleware;

use SulCompany\Router\Request;
use SulCompany\Router\Response;

class AuthMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        // executa antes do controller
        return true;
    }

    public function after(Request $request, Response $response): void
    {
        // executa depois do controller
    }
}
Regras:

before() deve retornar bool

after() retorna void

Uma classe pode implementar ambos, só um, ou nenhum (ignorada)

before()
Executa antes do controller.

Assinatura
php
public function before(Request $request, Response $response): bool;
Valores de Retorno
Retorno	Efeito
true	Continua para o próximo middleware / controller
false	Para a pipeline — nenhum controller roda
Exemplo: Autenticação
php
final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->auth->check()) {
            if ($request->expectsJson()) {
                $response->json(['error' => 'Unauthenticated'], 401);
            } else {
                $response->redirect('/login');
            }
            return false;  // para
        }
        return true;  // continua
    }
}
Exemplo: Log de Requisições
php
final class LogMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function before(Request $request, Response $response): bool
    {
        $this->logger->info('Request', [
            'method' => $request->method(),
            'path'   => $request->path(),
            'ip'     => $request->ip(),
        ]);
        return true;
    }
}
Exemplo: Verificação CSRF (apenas POST)
php
final class CsrfMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        if (!$request->isMethod('POST')) {
            return true;
        }

        $token = $request->post('_token') ?? $request->header('x-csrf-token');

        if (!$token || $token !== session('_token')) {
            $response->json(['error' => 'Invalid CSRF token'], 419);
            return false;
        }

        return true;
    }
}
after()
Executa depois do controller.

Assinatura
php
public function after(Request $request, Response $response): void;
Valor de Retorno
Nenhum (void). O after() não pode parar a pipeline.

Exemplo: Adicionar Header de Response
php
final class CorsMiddleware
{
    public function after(Request $request, Response $response): void
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE');
    }
}
Exemplo: Log de Response
php
final class ResponseLogMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function after(Request $request, Response $response): void
    {
        $this->logger->info('Response', [
            'status' => $response->getStatusCode(),
            'path'   => $request->path(),
        ]);
    }
}
Exemplo: Modificar HTML (injetar banner)
php
final class BannerMiddleware
{
    public function after(Request $request, Response $response): void
    {
        $body = $response->getBody();

        if (is_string($body) && str_contains($body, '<body')) {
            $body = str_replace('<body', '<body data-banner="1"', $body);
            $response->html($body, $response->getStatusCode());
        }
    }
}
⚠️ Cuidado: after() roda depois do controller, mas antes do send(). Modificar o body aqui funciona.

Registrando Middleware
Global
Executa em toda requisição:

php
$router->addGlobalMiddleware(SessionRestoreMiddleware::class);
Vários de uma vez:

php
$router->addGlobalMiddleware([
    SessionRestoreMiddleware::class,
    LogMiddleware::class,
]);
Grupo
Executa em todas as rotas do grupo:

php
$router->group('/admin', middleware: [AuthAdminMiddleware::class], callback: function ($r) {
    // ...
});
Rota
Executa apenas naquela rota:

php
$router->get('/admin/report', [ReportController::class, 'export'], middleware: [ReportPermissionMiddleware::class]);
Combinado
Os três se aplicam:

php
$router->addGlobalMiddleware(LogMiddleware::class);           // global

$router->group('/admin', middleware: [AuthAdminMiddleware::class], callback: function ($r) {  // grupo
    $r->get('/report', [ReportController::class, 'export'], middleware: [ReportPermissionMiddleware::class]);  // rota
});
Execução:

Global before

Grupo before

Rota before

Controller

Rota after

Grupo after

Global after

Tipos de Middleware
Tipo	Registro	Escopo	Quando usar
Global	addGlobalMiddleware()	Toda requisição	Sessão, logging, CORS
Grupo	group(..., middleware: [...])	Rotas do grupo	Auth para /admin, API key para /api
Rota	get(..., middleware: [...])	Rota única	Permissões pontuais
Ordem de Execução
Middlewares rodam na ordem em que são registrados, em duas fases:

text
Requisição
  │
  ├─ Global before #1
  ├─ Global before #2
  ├─ Grupo before #1
  ├─ Rota before #1
  │
  ├─ Controller
  │
  ├─ Rota after #1      ← invertido
  ├─ Grupo after #1     ← invertido
  ├─ Global after #2    ← invertido
  └─ Global after #1    ← invertido
  │
Resposta
Regra chave: after() roda em ordem reversa.

Por Que Ordem Reversa?
Porque after() serve para "desfazer" ou "finalizar" o que before() configurou. Exemplo:

php
class TimerMiddleware
{
    private float $start;

    public function before(...): bool { $this->start = microtime(true); return true; }
    public function after(...): void  { /* duração = agora - $this->start */ }
}
Se você tem Timer envolvendo Auth, quer que Timer.after rode por último para medir o tempo total.

Interrupção Cancela after()
Se um middleware retorna false do before(), nenhum after() roda — para nenhum middleware.

text
Requisição
  │
  ├─ Global before #1 (true)
  ├─ Global before #2 (false) ← PARA AQUI
  │
  └─ Response (construída pelo #2)
Nenhum after() é chamado.

Interrompendo a Pipeline
Para parar a pipeline, retorne false do before() e defina uma Response.

php
public function before(Request $request, Response $response): bool
{
    if (!authorized()) {
        $response->json(['error' => 'Forbidden'], 403);
        return false;
    }
    return true;
}
O Que o Dispatcher Faz
Quando um middleware retorna false:

A pipeline para

$dispatcher->error() é definido com o status da Response (ou 403 se vazio)

A Response construída pelo middleware é enviada

O controller não roda

Lendo o Status
php
$ok = $dispatcher->dispatch();
if (!$ok) {
    $status = $dispatcher->error();  // 403, 401, 302, ...
}
Múltiplos Builders de Response
Você pode chamar ->status() e ->json()/->html() em qualquer ordem:

php
$response
    ->status(403)
    ->header('X-Reason', 'no-permission')
    ->json(['error' => 'Forbidden']);
return false;
O Dispatcher lê getStatusCode() da Response.

Modificando a Response
Você pode modificar a Response sem parar a pipeline:

php
public function before(Request $request, Response $response): bool
{
    $response->header('X-Request-Id', uniqid());
    return true;  // continua
}
O header é preservado quando o controller retorna sua própria Response.

⚠️ Importante: se o controller retornar uma nova Response, as mutações do $response injetado são perdidas.

Para preservar mutações, o controller deve retornar o mesmo $response (ou não retornar nada).

Padrão Seguro
php
// Middleware
public function before(Request $request, Response $response): bool
{
    $response->header('X-App', 'SulCompany');
    return true;
}

// Controller — DEVE retornar a response injetada
public function index(Response $response): Response
{
    return $response->html('<h1>Olá</h1>');
}
Injeção de Dependências
Construtores de middleware são resolvidos pelo container (ou autowiring).

php
final class AuthMiddleware
{
    public function __construct(
        private AuthService $auth,
        private LoggerInterface $logger
    ) {}

    public function before(Request $request, Response $response): bool
    {
        $this->logger->debug('Auth check', ['user' => $this->auth->user()?->id]);
        return $this->auth->check();
    }
}
O Router instancia o middleware por requisição (não singleton) a menos que o container retorne uma instância compartilhada.

💡 Dica: para dependências compartilhadas (logger, DB), registre como singleton() no container.

Padrões Comuns
Guard de Auth (API + HTML)
php
final class AuthMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if ($this->auth->check()) {
            return true;
        }

        if ($request->expectsJson()) {
            $response->json(['error' => 'Unauthenticated'], 401);
        } else {
            $response->redirect('/login', 302);
        }

        return false;
    }
}
Guard de Role
php
final class AdminMiddleware
{
    public function __construct(private AuthService $auth) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->auth->user()?->hasRole('admin')) {
            $response->json(['error' => 'Forbidden'], 403);
            return false;
        }
        return true;
    }
}
Restauração de Sessão
php
final class SessionRestoreMiddleware
{
    public function __construct(private Session $session) {}

    public function before(Request $request, Response $response): bool
    {
        $this->session->start();
        return true;
    }

    public function after(Request $request, Response $response): void
    {
        $this->session->save();
    }
}
Rate Limit (simplificado)
php
final class RateLimitMiddleware
{
    public function __construct(private RateLimiter $limiter) {}

    public function before(Request $request, Response $response): bool
    {
        $key = $request->ip() . ':' . $request->path();

        if (!$this->limiter->attempt($key, max: 60, window: 60)) {
            $response
                ->status(429)
                ->header('Retry-After', '60')
                ->json(['error' => 'Too Many Requests']);
            return false;
        }

        return true;
    }
}
CORS
php
final class CorsMiddleware
{
    public function before(Request $request, Response $response): bool
    {
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if ($request->isMethod('OPTIONS')) {
            $response->status(204);
            return false;  // não roda o controller para preflight
        }

        return true;
    }
}
Modo de Manutenção
php
final class MaintenanceMiddleware
{
    public function __construct(private Config $config) {}

    public function before(Request $request, Response $response): bool
    {
        if (!$this->config->get('maintenance.enabled')) {
            return true;
        }

        if ($this->config->get('maintenance.allow_ips') && in_array($request->ip(), $this->config->get('maintenance.allow_ips'))) {
            return true;
        }

        $response->html('<h1>Em manutenção</h1>', 503);
        return false;
    }
}
Erros Comuns
Middleware Não Executa
Sintoma: você registra um middleware mas ele não executa.

Causas possíveis:

Não registrado onde você pensa — verifique global/grupo/rota

Typo no nome da classe — AuthMiddleware vs AuthMidleware (erro de digitação)

Classe não autoloadável — verifique namespace

Valor de Retorno de before() Ignorado
Sintoma: pipeline continua mesmo após return false.

Causa: before() não retorna bool (retorna null ou nada).

Correção:

php
public function before(Request $request, Response $response): bool
{
    // ...
    return true;  // ou false — DEVE retornar bool
}
Mutações de Response Perdidas
Sintoma: headers definidos em before() não aparecem na response final.

Causa: o controller retorna uma nova Response em vez de mutar a injetada.

Correção (recomendada): controller deve return $response->... (a injetada).

php
public function index(Response $response): Response
{
    return $response->html('<h1>Olá</h1>');  // mesmo objeto
}
after() Não Executa
Sintoma: after() não roda quando um before() retornou false.

Causa: por design — se a pipeline foi parada, nenhum after() roda.

Correção: coloque lógica de limpeza no middleware que para a pipeline.

Middleware Sem before()
Sintoma: classe apenas com after() não executa before().

Causa: por design — classes sem before() são ignoradas na fase before.

Correção: implemente before() (pode apenas return true).

Veja Também
Rotas — registrando rotas com middleware

Grupos — middleware de grupo

Controllers — valores de retorno de controller

Tratamento de Erros — 401, 403, responses customizadas

Container — DI para middleware

← Voltar ao README | 🇺🇸 English