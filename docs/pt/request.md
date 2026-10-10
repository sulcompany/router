Request
← Voltar ao README | 🇺🇸 English

O objeto Request representa a requisição HTTP atual. Ele fornece acesso ao método, caminho, headers, query string, corpo POST, corpo JSON, arquivos enviados, IP do cliente e helpers de negociação de conteúdo.

Índice
Visão Geral

Rota / Caminho / Método

Headers

Dados de Entrada

Arquivos

Ambiente

Negociação de Conteúdo

Referência Completa de Métodos

Padrões Comuns

Erros Comuns

Visão Geral
O Request é criado pelo Dispatcher no início de cada requisição e passado para:

Middleware (before / after)

Controllers (via type-hint Request $request)

Closures (argumento $request)

Classe
php
namespace SulCompany\Router;

class Request
{
    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $json = [],
        array $files = [],
        string $method = 'GET',
        string $path = '/'
    );
}
Nota: você quase nunca instancia Request sozinho — o Dispatcher faz isso.

Mutabilidade
Diferente do PSR-7, o Request é mutável. Dois setters são chamados internamente pelo Dispatcher:

setRoute(Route $route) — após o match

setParams(array $params) — após o match (se implementado)

Rota / Caminho / Método
route(): ?Route
Retorna o objeto Route correspondido, ou null se a requisição ainda não foi despachada.

php
$route = $request->route();

if ($route !== null) {
    echo $route->getName();                       // "user.show"
    echo $route->getAttribute('context', 'ecommerce');  // "admin" ou "ecommerce"
}
method(): string
Retorna o método HTTP, em maiúsculas.

php
$request->method();  // "GET", "POST", "PUT", "PATCH", "DELETE"
isMethod(string $method): bool
Verificação case-insensitive.

php
if ($request->isMethod('post')) {
    // ...
}
path(): string
Retorna o caminho da requisição, sem query string.

php
// URL: https://meusite.com/user/42?ref=email
$request->path();  // "/user/42"
Headers
header(?string $name = null): array|string|null
Retorna todos os headers ou um header (case-insensitive).

php
// Todos os headers
$headers = $request->header();
// → ['accept' => '...', 'user-agent' => '...', ...]

// Um header
$accept = $request->header('accept');
// → "text/html,..."

// Case-insensitive
$request->header('Accept');       // mesmo que header('accept')
$request->header('ACCEPT');       // mesmo
Retorna null se o header não existe.

userAgent(): ?string
Atalho para header('user-agent').

php
$ua = $request->userAgent();
// → "Mozilla/5.0 ..."
Origem dos Headers
Headers são extraídos de $_SERVER (chaves começando com HTTP_), em minúsculas, separadas por hífen:

text
HTTP_USER_AGENT      → user-agent
HTTP_ACCEPT          → accept
HTTP_X_REQUESTED_WITH → x-requested-with
Nota: Content-Type e Content-Length não estão em $_SERVER['HTTP_*'] — vêm de CONTENT_TYPE e CONTENT_LENGTH. O Router não expõe isso como headers. Use $request->header('content-type') apenas se seu servidor definir HTTP_CONTENT_TYPE.

Dados de Entrada
GET (query string)
php
$request->get(): array;
$request->get('page'): mixed;
$request->get('page', 1): mixed;  // default
Exemplo:

php
// URL: /search?q=router&page=2
$request->get('q');       // "router"
$request->get('page');    // "2"
$request->get('limit', 10); // 10 (default)
$request->get();          // ['q' => 'router', 'page' => '2']
POST (corpo de formulário)
php
$request->post(): array;
$request->post('name'): mixed;
$request->post('name', 'Anônimo'): mixed;
Exemplo:

php
// Form: name=Alice&email=alice@example.com
$request->post('name');   // "Alice"
$request->post();         // ['name' => 'Alice', 'email' => '...']
JSON (corpo da requisição)
php
$request->json(): array;
$request->json('field'): mixed;
$request->json('field', 'default'): mixed;
Exemplo:

php
// Content-Type: application/json
// Body: {"name": "Alice", "tags": ["a","b"]}
$request->json('name');    // "Alice"
$request->json('tags');    // ['a', 'b']
$request->json();          // ['name' => 'Alice', 'tags' => [...]]
Nota: JSON só é parseado quando:

Método é POST, PUT, PATCH ou DELETE

Content-Type contém application/json

Todos Combinados
php
$request->all(): array;
$request->all('key'): mixed;
Ordem de mesclagem: json sobrescreve post sobrescreve get.

Chave em	Chave em	Chave em	all() retorna
GET	POST	JSON	Valor JSON
GET	POST	—	Valor POST
GET	—	—	Valor GET
Nota: all() não inclui arquivos.

Corpo Cru
O Router não expõe o corpo cru da requisição. Se precisar:

php
$raw = file_get_contents('php://input');
Arquivos
file(?string $key = null, $default = null)
Retorna todos os arquivos enviados ou um arquivo.

php
// Todos
$request->file();
// → ['avatar' => ['name' => '...', 'tmp_name' => '...', ...]]

// Um
$request->file('avatar');
// → ['name' => 'photo.jpg', 'tmp_name' => '/tmp/phpXXXX', 'size' => 12345, ...]
Retorna a entrada crua de $_FILES. Sem processamento — o formato do array segue o padrão nativo do PHP.

Array de Arquivos
Para name="files[]" (múltiplos), $_FILES é reestruturado pelo PHP. O Router retorna como está.

Ambiente
ip(): ?string
Retorna o IP do cliente, respeitando X-Forwarded-For:

php
$request->ip();  // "203.0.113.42"
Lógica:

Se HTTP_X_FORWARDED_FOR está definido → primeiro IP da lista

Caso contrário → REMOTE_ADDR

Retorna null se nenhum está disponível

⚠️ Nota de segurança: X-Forwarded-For é controlado pelo cliente a menos que você esteja atrás de um proxy confiável. Não use para decisões de segurança a menos que tenha verificado o proxy.

userAgent(): ?string
Coberto em Headers.

Negociação de Conteúdo
Helpers para detectar o que o cliente espera.

wantsJson(): bool
Retorna true se o header Accept contém application/json.

php
// Accept: application/json
$request->wantsJson();  // true
isAjax(): bool
Retorna true se X-Requested-With: XMLHttpRequest.

php
// X-Requested-With: XMLHttpRequest
$request->isAjax();  // true
expectsJson(): bool
Combina ambos. Use este.

php
// Qualquer um dos acima
$request->expectsJson();  // true
Comparação
Header	wantsJson	isAjax	expectsJson
Accept: application/json	✅	❌	✅
X-Requested-With: XMLHttpRequest	❌	✅	✅
Ambos	✅	✅	✅
Nenhum	❌	❌	❌
Exemplo
php
public function store(Request $request, Response $response): Response
{
    if ($request->expectsJson()) {
        return $response->json(['ok' => true]);
    }

    return $response->redirect('/success');
}
Referência Completa de Métodos
Método	Retorno	Descrição
route()	?Route	Rota correspondida
method()	string	Método HTTP (maiúsculas)
isMethod($method)	bool	Verificação case-insensitive
path()	string	Caminho da requisição
header($name = null)	array|string|null	Todos ou um header
userAgent()	?string	Atalho do User-Agent
get($key = null, $default = null)	mixed	Query string
post($key = null, $default = null)	mixed	Corpo POST
json($key = null, $default = null)	mixed	Corpo JSON
file($key = null, $default = null)	mixed	Arquivos enviados
all($key = null, $default = null)	mixed	Dados mesclados (GET + POST + JSON)
ip()	?string	IP do cliente
wantsJson()	bool	Accept: application/json
isAjax()	bool	X-Requested-With: XMLHttpRequest
expectsJson()	bool	wantsJson() || isAjax()
Padrões Comuns
Resposta Condicional
php
public function store(Request $request, Response $response): Response
{
    // ... faz o trabalho ...

    if ($request->expectsJson()) {
        return $response->json(['ok' => true], 201);
    }

    return $response->redirect('/users');
}
Obter Valor com Fallback
php
$page = (int) $request->get('page', 1);
$limit = (int) $request->get('limit', 20);
Detectar Método em Uma Linha
php
if ($request->isMethod('POST')) {
    // trata formulário
}
Acesso Unificado a Dados
php
// Funciona para query string, formulário E JSON
$name = $request->all('name');
Acessando a Rota
php
public function show(Request $request, Response $response): Response
{
    $context = $request->route()?->getAttribute('context') ?? 'ecommerce';
    return $response->json(['context' => $context]);
}
IP do Cliente Atrás de Proxy
php
$ip = $request->ip();
// Se você confia no seu proxy, isto é seguro.
// Caso contrário, use REMOTE_ADDR diretamente.
Erros Comuns
header() Retorna null
Sintoma: $request->header('content-type') retorna null.

Causa: Content-Type não está em $_SERVER['HTTP_*']. Está em $_SERVER['CONTENT_TYPE'].

Correção: leia diretamente:

php
$contentType = $_SERVER['CONTENT_TYPE'] ?? null;
Corpo JSON Está Vazio
Sintoma: $request->json('field') retorna null para uma requisição JSON.

Causas:

Content-Type não é application/json — o Router só parseia JSON quando este header está presente

Método não é POST/PUT/PATCH/DELETE — JSON só é parseado para esses

JSON malformado — sintaxe inválida

Correção:

bash
# Teste com curl:
curl -X POST https://meusite.com/api \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice"}'
all() com Valor Faltando
Sintoma: um valor POST não está em all().

Causa: um valor JSON com a mesma chave sobrescreveu.

Correção: use post() diretamente se precisar do valor POST:

php
$name = $request->post('name');  // específico
ip() Retorna null
Sintoma: $request->ip() é null.

Causa: rodando em CLI ou servidor sem REMOTE_ADDR.

Correção: fallback no seu código:

php
$ip = $request->ip() ?? '0.0.0.0';
Método Errado Detectado
Sintoma: $request->method() é GET mas você enviou POST.

Causa: o servidor pode reescrever. Verifique com var_dump($_SERVER['REQUEST_METHOD']).

Correção: para method spoofing, use _method no corpo POST:

html
<form method="POST">
    <input type="hidden" name="_method" value="PUT">
    ...
</form>
O Router honra _method (apenas POST) e converte para PUT.

Veja Também
Response — construindo responses

Controllers — usando Request em controllers

Middleware — usando Request em middleware

Rotas — acessando parâmetros da rota

← Voltar ao README | 🇺🇸 English