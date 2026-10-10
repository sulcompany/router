Container (DI)
← Back to README | 🇵🇹 Português

The Container is a PSR-11 compliant dependency injection container with autowiring. It is optional — the Router works without it.

Table of Contents
Overview

Installation

API

Registering Services

Resolving Services

Autowiring

Strict has()

Exceptions

Using with the Router

Common Patterns

Common Errors

Overview
The Container class:

Implements Psr\Container\ContainerInterface

Supports bind (transient), singleton (shared), and instance (pre-built)

Has autowiring via reflection

Detects circular dependencies

Throws PSR-11 exceptions

When to Use
Use the container when:

Your controllers have constructor dependencies

You want to reuse services (DB, logger, cache)

You want autowiring (auto-resolve constructor params)

Skip the container when:

Your controllers are stateless (no constructor)

You use closures for everything

You want minimal complexity

Installation
The container is included in the Router. No extra install.

bash
composer require sulcompany/router
Requirement: psr/container ^2.0 (installed automatically).

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
Method Reference
Method	Description	Returns
bind($id, $concrete)	Register a transient definition	void
singleton($id, $concrete)	Register a shared definition	void
instance($id, $instance)	Register a pre-built instance	void
get($id)	Resolve	mixed
has($id)	Check if registered	bool
Registering Services
bind() — Transient
Every get() creates a new instance.

php
$container->bind(LoggerInterface::class, function ($c) {
    return new FileLogger('/var/log/app.log');
});
php
$a = $container->get(LoggerInterface::class);
$b = $container->get(LoggerInterface::class);

var_dump($a === $b);  // false
When to use: stateless services, request-scoped objects, things that should not be shared.

singleton() — Shared
The first get() creates the instance, subsequent calls return the same.

php
$container->singleton(PDO::class, function ($c) {
    return new PDO('mysql:host=localhost;dbname=app', 'user', 'pass');
});
php
$a = $container->get(PDO::class);
$b = $container->get(PDO::class);

var_dump($a === $b);  // true
When to use: database connections, loggers, config, caches, anything expensive.

instance() — Pre-built
Register an already-created object.

php
$config = new Config([
    'db.host' => 'localhost',
    'app.env' => 'production',
]);

$container->instance(Config::class, $config);
php
$c = $container->get(Config::class);
var_dump($c === $config);  // true
When to use: objects you created outside the container (config arrays, third-party clients).

Registering a Class Directly
You can register an object (not a factory):

php
$container->singleton(SomeService::class, new SomeService());
But this instantiates immediately, even if never used. Prefer factories:

php
$container->singleton(SomeService::class, fn() => new SomeService());
Closure Signature
Factories receive the container as the first argument:

php
$container->bind(UserRepository::class, function ($c) {
    return new UserRepository($c->get(PDO::class));
});
Resolving Services
get(string $id): mixed
Resolves the service.

php
$logger = $container->get(LoggerInterface::class);
$pdo = $container->get(PDO::class);
Resolution Order
Pre-built instance — if registered via instance()

Definition — if registered via bind() or singleton()

Autowiring — if $id is a class that can be instantiated

Exception — NotFoundException if none apply

has(string $id): bool
Checks if the service is explicitly registered.

php
$container->has(LoggerInterface::class);  // true (registered)
$container->has(\stdClass::class);        // false (not registered)
Important: has() returns true only for registered entries. Autowiring does not count. See Strict has().

Autowiring
If you call get() with an unregistered class, the container tries to resolve it via reflection.

Rules
For each constructor parameter:

Parameter	Resolution
Has a non-builtin type and class exists	Resolve recursively
Has a default value	Use the default
Is nullable (no default)	Use null
Otherwise	Throw ContainerException
Example
php
class Mailer
{
    public function __construct(
        private LoggerInterface $logger,     // resolved via container
        private string $from = 'noreply@example.com'  // default
    ) {}
}
php
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));

$mailer = $container->get(Mailer::class);
// LoggerInterface resolved, $from = "noreply@example.com"
Autowiring is Transient
Every get() with autowiring creates a new instance:

php
$a = $container->get(Mailer::class);
$b = $container->get(Mailer::class);

var_dump($a === $b);  // false
To share, register with singleton():

php
$container->singleton(Mailer::class, fn($c) => new Mailer($c->get(LoggerInterface::class)));
When Autowiring Fails
Autowiring cannot:

Instantiate interfaces or abstract classes

Resolve parameters with no default and no type

Resolve types that don't exist

php
class Foo
{
    public function __construct(private LoggerInterface $logger) {}
}

$container->get(Foo::class);
// ❌ ContainerException: cannot resolve LoggerInterface
Fix: register the interface:

php
$container->bind(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
$container->get(Foo::class);  // ✅
Builtin Types
Builtin types (string, int, array, etc.) are not autowired — they fall back to defaults or null:

php
class Config
{
    public function __construct(private string $env) {}
}

$container->get(Config::class);
// ❌ ContainerException (no default for $env)
Fix: use bind() with a factory.

Strict has()
has() returns true only for explicitly registered entries. Autowiring does not count.

Example
php
class Foo { public function __construct() {} }

$container->has(Foo::class);  // false (not registered)
$container->get(Foo::class);  // ✅ works (autowiring)
Why Strict?
Because has() answers "is there an entry for this id?" — not "can it be resolved?". This matches PSR-11 semantics.

Checking Resolution
If you need to check "can this be resolved?", use a try/catch:

php
try {
    $container->get(Foo::class);
    $canResolve = true;
} catch (\Throwable) {
    $canResolve = false;
}
Exceptions
The container throws two exception types, both PSR-11 compliant.

NotFoundException
Thrown when get() is called with an unknown ID.

php
use SulCompany\Router\DI\Exceptions\NotFoundException;

try {
    $container->get('nonexistent');
} catch (NotFoundException $e) {
    echo "Service not found: " . $e->getMessage();
}
Implements Psr\Container\NotFoundExceptionInterface.

ContainerException
Thrown when resolution fails (circular dependency, invalid type, etc.).

php
use SulCompany\Router\DI\Exceptions\ContainerException;

try {
    $container->get(CircularA::class);
} catch (ContainerException $e) {
    echo "Cannot resolve: " . $e->getMessage();
}
Implements Psr\Container\ContainerExceptionInterface.

Catching Both
php
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

try {
    $service = $container->get(SomeService::class);
} catch (NotFoundExceptionInterface $e) {
    // not registered
} catch (ContainerExceptionInterface $e) {
    // failed to resolve
}
Circular Dependency
php
class A { public function __construct(public B $b) {} }
class B { public function __construct(public A $a) {} }

$container->get(A::class);
// ❌ ContainerException: Circular dependency detected
The container tracks the resolution stack and refuses to recurse.

Using with the Router
Pass the container to the Dispatcher:

php
use SulCompany\Router\DI\Container;
use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

$container = new Container();

$container->singleton(PDO::class, fn() => new PDO('mysql:...'));
$container->singleton(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));

$router = new Router('https://mysite.com');
$router->get('/users', [UserController::class, 'index']);

$dispatcher = new Dispatcher($router, $container);
$dispatcher->dispatch();
Controller Resolution
When the Dispatcher needs to instantiate a controller, it:

Asks the container: $container->has(UserController::class)

If yes → $container->get(UserController::class)

If no → autowires via reflection

Tip: you don't need to register controllers — autowiring handles them.

Middleware Resolution
Middleware classes are resolved the same way:

php
$router->addGlobalMiddleware(AuthMiddleware::class);
// Resolved via container → autowiring → constructor DI
Optional Container
The Router works without a container:

php
$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
In that case, controllers and middleware are instantiated via reflection only (no shared services).

Common Patterns
Singleton DB Connection
php
$container->singleton(PDO::class, function () {
    return new PDO(
        'mysql:host=localhost;dbname=app;charset=utf8mb4',
        'user',
        'pass',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
});
Interface → Implementation
php
$container->bind(LoggerInterface::class, FileLogger::class);  // ❌ won't work
Note: bind() expects a callable or object, not a class-string. Use a factory:

php
$container->bind(LoggerInterface::class, fn() => new FileLogger('/tmp/log'));
Config Array
php
$config = new Config(require __DIR__ . '/../config/app.php');
$container->instance(Config::class, $config);
Repository with Dependencies
php
$container->bind(UserRepository::class, function ($c) {
    return new UserRepository(
        $c->get(PDO::class),
        $c->get(LoggerInterface::class)
    );
});
Shared Cache
php
$container->singleton(CacheInterface::class, function () {
    return new RedisCache(new Redis('127.0.0.1'));
});
Test Isolation
In tests, override services with mocks:

php
$container->instance(LoggerInterface::class, new NullLogger());
$container->instance(PDO::class, $mockPdo);
Common Errors
NotFoundException
Symptom: Service or class 'X' not found.

Cause: the ID isn't registered, and it's not a class.

Fix: register it:

php
$container->bind('X', fn() => new X());
Or check for typos.

ContainerException — Cannot Resolve Parameter
Symptom: Cannot resolve parameter $foo in Bar.

Cause: $foo has no default, no type, or the type can't be resolved.

Fix: add a default, register the type, or use a factory.

Circular Dependency
Symptom: Circular dependency detected.

Cause: A needs B, B needs A (directly or indirectly).

Fix: break the cycle with setter injection or a lazy factory.

php
class A
{
    public function __construct(private LazyB $b) {}
}
bind() Doesn't Accept Class Strings
Symptom: TypeError: bind(): Argument #2 must be of type callable|object.

Cause: you passed UserRepository::class (a string).

Fix:

php
$container->bind(UserRepository::class, fn($c) => new UserRepository($c->get(PDO::class)));
Autowiring Instantiates New Objects
Symptom: get(SomeService::class) returns a new instance every time.

Cause: autowiring is transient.

Fix: register as singleton:

php
$container->singleton(SomeService::class, fn() => new SomeService());
has() Returns false for Autowireable Classes
Symptom: $container->has(Foo::class) is false, but get(Foo::class) works.

Cause: by design — has() is strict.

Fix: use get() inside try/catch if you need "can it resolve?".

See Also
Controllers — DI in controllers

Middleware — DI in middleware

Error Handling — registering the error handler

PSR-11 — official specification

← Back to README | 🇵🇹 Português