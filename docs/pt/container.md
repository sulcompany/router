Container (DI)
← Voltar ao README | 🇺🇸 English

O Container é um container de injeção de dependências compatível com PSR-11, com autowiring. É opcional — o Router funciona sem ele.

Índice
Visão Geral

Instalação

API

Registrando Serviços

Resolvendo Serviços

Autowiring

has() Estrito

Exceções

Usando com o Router

Padrões Comuns

Erros Comuns

Visão Geral
A classe Container:

Implementa Psr\Container\ContainerInterface

Suporta bind (transient), singleton (compartilhado) e instance (pré-construído)

Tem autowiring via reflection

Detecta dependências circulares

Lança exceções PSR-11

Quando Usar
Use o container quando:

Seus controllers têm dependências no construtor

Você quer reutilizar serviços (DB, logger, cache)

Você quer autowiring (resolver automaticamente parâmetros do construtor)

Pule o container quando:

Seus controllers são sem estado (sem construtor)

Você usa closures pra tudo

Você quer complexidade mínima

Instalação
O container está incluído no Router. Sem instalação extra.

bash
composer require sulcompany/router
Requisito: psr/container ^2.0 (instalado automaticamente).

API
php
namespace SulCompany\Router\DI;

class Container implements ContainerInterface
{
    public function bind(string $id, callable|object $concrete): void;
    public function singleton(string $id, callable|object $concrete): void;
    public function instance(string $id, object $instance): void;
    public function get(string $id): mixed;
    public function has(string $id): bool;
}
Referência de Métodos
Método	Descrição	Retorna
bind($id, $concrete)	Registra uma definição transient	void
singleton($id, $concrete)	Registra uma definição compartilhada	void
instance($id, $instance)	Registra uma instância pré-construída	void
get($id)	Resolve	mixed
has($id)	Verifica se registrado	bool
Registrando Serviços
bind() — Transient
Cada get() cria uma nova instância.

php
$container->bind(LoggerInterface::class, function ($c) {
    return new FileLogger('/var/log/app.log');
});
php
$a = $container->get(LoggerInterface::class);
$b = $container->get(LoggerInterface::class);

var_dump($a === $b);  // false
Quando usar: serviços sem estado, objetos com escopo de requisição, coisas que não devem ser compartilhadas.

singleton() — Compartilhado
O primeiro get() cria a instância, chamadas subsequentes retornam a mesma.

php
$container->singleton(PDO::class, function ($c) {
    return new PDO('mysql:host=localhost;dbname=app', 'user', 'pass');
});
php
$a = $container->get(PDO::class);
$b = $container->get(PDO::class);

var_dump($a === $b);  // true
Quando usar: conexões de banco, loggers, config, caches, qualquer coisa cara.

instance() — Pré-construído
Registra um objeto já criado.

php
$config = new Config([
    'db.host' => 'localhost',
    'app.env' => 'production',
]);

$container->instance(Config::class, $config);
php
$c = $container->get(Config::class);
var_dump($c === $config);  // true
Quando usar: objetos que você criou fora do container (arrays de config, clientes de terceiros).

Registrando uma Classe Diretamente
Você pode registrar um objeto (não uma factory):

php
$container->singleton(SomeService::class, new SomeService());
Mas isto instancia imediatamente, mesmo se nunca for usado. Prefira factories:

php
$container->singleton(SomeService::class, fn() => new SomeService());
Assinatura de Closure
Factories recebem o container como primeiro argumento:

php
$container->bind(UserRepository::class, function ($c) {
    return new UserRepository($c->get(PDO::class));
});
Resolvendo Serviços
get(string $id): mixed
Resolve o serviço.

php
$logger = $container->get(LoggerInterface::class);
$pdo = $container->get(PDO::class);
Ordem de Resolução
Instância pré-construída — se registrada via instance()

Definição — se registrada via bind() ou singleton()

Autowiring — se $id é uma classe que pode ser instanciada

Exceção — NotFoundException se nenhuma se aplica

has(string $id): bool
Verifica se o serviço está explicitamente registrado.

php
$container->has(LoggerInterface::class);  // true (registrado)
$container->has(\stdClass::class);        // false (não registrado)
Importante: has() retorna true apenas para entradas registradas. Autowiring não conta. Veja has() Estrito.

Autowiring
Se você chamar get() com uma classe não registrada, o container tenta resolvê-la via reflection.

Regras
Para cada parâmetro do construtor:

Parâmetro	Resolução
Tem um tipo não-builtin e a classe existe	Resolve recursivamente
Tem um valor default	Usa o default
É nullable (sem default)	Usa null
Caso contrário	Lança ContainerException
Exemplo
php
class Mailer
{
    public function __construct(
        private LoggerInterface $logger,     // resolvido via container
        private string $from = 'noreply@example.com'  // default
    ) {}
}
php
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));

$mailer = $container->get(Mailer::class);
// LoggerInterface resolvido, $from = "noreply@example.com"
Autowiring é Transient
Cada get() com autowiring cria uma nova instância:

php
$a = $container->get(Mailer::class);
$b = $container->get(Mailer::class);

var_dump($a === $b);  // false
Para compartilhar, registre com singleton():

php
$container->singleton(Mailer::class, fn($c) => new Mailer($c->get(LoggerInterface::class)));
Quando Autowiring Falha
Autowiring não consegue:

Instanciar interfaces ou classes abstratas

Resolver parâmetros sem default e sem tipo

Resolver tipos que não existem

php
class Foo
{
    public function __construct(private LoggerInterface $logger) {}
}

$container->get(Foo::class);
// ❌ ContainerException: cannot resolve LoggerInterface
Correção: registre a interface:

php
$container->bind(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
$container->get(Foo::class);  // ✅
Tipos Builtin
Tipos builtin (string, int, array, etc.) não são autowired — caem em defaults ou null:

php
class Config
{
    public function __construct(private string $env) {}
}

$container->get(Config::class);
// ❌ ContainerException (sem default para $env)
Correção: use bind() com uma factory.

has() Estrito
has() retorna true apenas para entradas explicitamente registradas. Autowiring não conta.

Exemplo
php
class Foo { public function __construct() {} }

$container->has(Foo::class);  // false (não registrado)
$container->get(Foo::class);  // ✅ funciona (autowiring)
Por Que Estrito?
Porque has() responde "existe uma entrada para este id?" — não "pode ser resolvido?". Isto alinha com a semântica PSR-11.

Verificando Resolução
Se você precisa verificar "isto pode ser resolvido?", use try/catch:

php
try {
    $container->get(Foo::class);
    $canResolve = true;
} catch (\Throwable) {
    $canResolve = false;
}
Exceções
O container lança dois tipos de exceção, ambos compatíveis com PSR-11.

NotFoundException
Lançada quando get() é chamado com um ID desconhecido.

php
use SulCompany\Router\DI\Exceptions\NotFoundException;

try {
    $container->get('nonexistent');
} catch (NotFoundException $e) {
    echo "Serviço não encontrado: " . $e->getMessage();
}
Implementa Psr\Container\NotFoundExceptionInterface.

ContainerException
Lançada quando a resolução falha (dependência circular, tipo inválido, etc.).

php
use SulCompany\Router\DI\Exceptions\ContainerException;

try {
    $container->get(CircularA::class);
} catch (ContainerException $e) {
    echo "Não foi possível resolver: " . $e->getMessage();
}
Implementa Psr\Container\ContainerExceptionInterface.

Capturando Ambas
php
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

try {
    $service = $container->get(SomeService::class);
} catch (NotFoundExceptionInterface $e) {
    // não registrado
} catch (ContainerExceptionInterface $e) {
    // falhou ao resolver
}
Dependência Circular
php
class A { public function __construct(public B $b) {} }
class B { public function __construct(public A $a) {} }

$container->get(A::class);
// ❌ ContainerException: Circular dependency detected
O container rastreia a pilha de resolução e recusa recursão.

Usando com o Router
Passe o container para o Dispatcher:

php
use SulCompany\Router\DI\Container;
use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

$container = new Container();

$container->singleton(PDO::class, fn() => new PDO('mysql:...'));
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));

$router = new Router('https://meusite.com');
$router->get('/users', [UserController::class, 'index']);

$dispatcher = new Dispatcher($router, $container);
$dispatcher->dispatch();
Resolução de Controller
Quando o Dispatcher precisa instanciar um controller, ele:

Pergunta ao container: $container->has(UserController::class)

Se sim → $container->get(UserController::class)

Se não → autowires via reflection

Dica: você não precisa registrar controllers — autowiring resolve.

Resolução de Middleware
Classes de middleware são resolvidas da mesma forma:

php
$router->addGlobalMiddleware(AuthMiddleware::class);
// Resolvido via container → autowiring → DI do construtor
Container Opcional
O Router funciona sem um container:

php
$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
Nesse caso, controllers e middleware são instanciados via reflection apenas (sem serviços compartilhados).

Padrões Comuns
Conexão de Banco Singleton
php
$container->singleton(PDO::class, function () {
    return new PDO(
        'mysql:host=localhost;dbname=app;charset=utf8mb4',
        'user',
        'pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
});
Interface → Implementação
php
$container->bind(LoggerInterface::class, FileLogger::class);  // ❌ não funciona
Nota: bind() espera um callable ou objeto, não uma class-string. Use uma factory:

php
$container->bind(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
Array de Config
php
$config = new Config(require __DIR__ . '/../config/app.php');
$container->instance(Config::class, $config);
Repositório com Dependências
php
$container->bind(UserRepository::class, function ($c) {
    return new UserRepository(
        $c->get(PDO::class),
        $c->get(LoggerInterface::class)
    );
});
Cache Compartilhado
php
$container->singleton(CacheInterface::class, function () {
    return new RedisCache(new Redis('127.0.0.1'));
});
Isolamento em Testes
Em testes, sobrescreva serviços com mocks:

php
$container->instance(LoggerInterface::class, new NullLogger());
$container->instance(PDO::class, $mockPdo);
Erros Comuns
NotFoundException
Sintoma: Service or class 'X' not found.

Causa: o ID não está registrado, e não é uma classe.

Correção: registre-o:

php
$container->bind('X', fn() => new X());
Ou verifique erros de digitação.

ContainerException — Não é Possível Resolver Parâmetro
Sintoma: Cannot resolve parameter $foo in Bar.

Causa: $foo não tem default, não tem tipo, ou o tipo não pode ser resolvido.

Correção: adicione um default, registre o tipo, ou use uma factory.

Dependência Circular
Sintoma: Circular dependency detected.

Causa: A precisa de B, B precisa de A (direta ou indiretamente).

Correção: quebre o ciclo com setter injection ou uma factory lazy.

php
class A
{
    public function __construct(private LazyB $b) {}
}
bind() Não Aceita Class Strings
Sintoma: TypeError: bind(): Argument #2 must be of type callable|object.

Causa: você passou UserRepository::class (uma string).

Correção:

php
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
Autowiring Instancia Novos Objetos
Sintoma: get(SomeService::class) retorna uma nova instância toda vez.

Causa: autowiring é transient.

Correção: registre como singleton:

php
$container->singleton(SomeService::class, fn() => new SomeService());
has() Retorna false para Classes Autowireable
Sintoma: $container->has(Foo::class) é false, mas get(Foo::class) funciona.

Causa: por design — has() é estrito.

Correção: use get() dentro de try/catch se precisar de "pode resolver?".

Veja Também
Controllers — DI em controllers

Middleware — DI em middleware

Tratamento de Erros — registrando o error handler

PSR-11 — especificação oficial

← Voltar ao README | 🇺🇸 English