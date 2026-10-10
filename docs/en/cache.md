Route Cache
← Back to README | 🇵🇹 Português

The Router can compile routes once and load them from a PHP file on every subsequent request. This dramatically reduces CPU usage by skipping regex compilation and route registration.

Table of Contents
Overview

When to Use

How It Works

Setup

Cache Methods

What Is Cached

What Is Not Cached

Invalidating the Cache

Cache File Format

Common Patterns

Common Errors

Overview
Route compilation (turning /user/{id} into a regex) is cheap, but on every request it adds up. The cache:

Compiles all routes once

Serializes them to a PHP file

On subsequent requests, loads the file directly

Performance Impact
Setup	Routes	Time (approx.)
No cache, 100 routes	100	~5-15 ms
With cache, 100 routes	100	~0.5-2 ms
Real numbers vary, but the cache typically saves 70-90% of route-matching overhead for large apps.

What "Compiling" Means
For each dynamic route (/user/{id}), the Router builds a regex:

text
/user/{id}  →  ~^/user/([^/]+)$~
This is done once per route. The cache stores the compiled regex, so the next request doesn't need to rebuild it.

When to Use
Use the cache when:
✅ You have many routes (>50)

✅ Your app runs in production

✅ Routes rarely change

✅ You want maximum performance

Skip the cache when:
❌ You're developing (routes change constantly)

❌ You have few routes (<20)

❌ You deploy frequently (extra step)

Recommendation: enable in production, disable in development.

How It Works
Request Flow Without Cache
text
Request
  ↓
Dispatcher created
  ↓
compile()       ← parses /user/{id} → regex
  ↓
dispatch()
Request Flow With Cache
text
Request
  ↓
Dispatcher created
  ↓
loadCache()     ← loads precompiled routes from file
  ↓
dispatch()
If the cache file doesn't exist, the Router falls back to compile() and writes the cache.

Setup
Basic Setup
php
$router = new Router('https://mysite.com');

$router->enableCache(__DIR__ . '/cache/routes.php');

if (!$router->loadCache()) {
    // Cache miss → register routes
    $router->get('/', [HomeController::class, 'index'], name: 'home');
    $router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
    // ... more routes ...

    // Compile and save
    $router->compile();
    $router->cacheCompiledRoutes();
}

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();
With index.php
php
<?php

declare(strict_types=1);

ob_start();

use SulCompany\Router\Dispatcher;
use SulCompany\Router\Router;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://mysite.com');
$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (!$router->loadCache()) {
    // Register all routes
    require __DIR__ . '/../routes/web.php';

    $router->compile();
    $router->cacheCompiledRoutes();
}

$dispatcher = new Dispatcher($router);
$dispatcher->dispatch();

while (ob_get_level() > 0) {
    ob_end_flush();
}
Cache Methods
enableCache(string $cacheFile): self
Registers the cache file path and enables the cache.

php
$router->enableCache('/path/to/routes.php');
The file doesn't need to exist yet — the Router creates it on the first cacheCompiledRoutes() call.

loadCache(): bool
Attempts to load the compiled routes from the cache file.

Return	Meaning
true	Cache loaded successfully — routes are ready
false	Cache not enabled, file missing, or invalid
php
if (!$router->loadCache()) {
    // register routes
}
compile(): void
Compiles routes into lookup structures (static hash + dynamic regex list).

Idempotent: calling compile() twice does nothing the second time (if already compiled).

cacheCompiledRoutes(): void
Writes the compiled routes to the cache file.

php
$router->compile();
$router->cacheCompiledRoutes();
Note: compile() is called automatically by cacheCompiledRoutes() if routes aren't compiled yet.

Method Summary
Method	Purpose
enableCache($file)	Enables the cache
loadCache()	Loads from cache
compile()	Compiles routes
cacheCompiledRoutes()	Writes the cache
What Is Cached
The cache file contains all routes with:

Field	Cached?	Example
method	✅	GET
path	✅	/user/{id}
handler	✅	UserController::class (string)
action	✅	show
name	✅	user.show
middlewares	✅	[AuthMiddleware::class]
regex	✅	~^/user/([^/]+)$~
paramNames	✅	['id']
attributes	✅	['context' => 'admin']
Route Names Index
The nameIndex is also cached:

php
[
    'user.show' => ['method' => 'GET', 'path' => '/user/{id}'],
    // ...
]
Used by urlFor().

Custom Attributes
Any attributes you set on a route via setAttribute() are preserved across cache loads.

php
$route->setAttribute('context', 'admin');
$route->setAttribute('version', 'v2');
// Both survive cache → load → dispatch
What Is Not Cached
1. Closure Handlers
Routes with closure handlers are skipped in the cache.

php
$router->get('/health', function ($req, $res) {
    return $res->json(['status' => 'ok']);
});
// ❌ Not cached — closures can't be serialized
Why: var_export() cannot serialize Closure objects.

Impact: on every request, closure routes must be registered even with cache enabled.

2. Services
The cache does not store container services. Only routes.

3. Config
The projectUrl is not cached. It's set on new Router(...).

Summary
Item	Cached?
Static routes	✅
Dynamic routes	✅
Name index	✅
Attributes	✅
Closure handlers	❌
Container services	❌
Config	❌
Invalidating the Cache
The cache does not auto-invalidate. You must delete or regenerate it when:

Adding a new route

Removing a route

Changing a route's path, name, or handler

Changing a middleware on a route

Manual Deletion
bash
rm storage/cache/routes.php
The next request triggers loadCache() → returns false → recompiles.

Regenerate on Deploy
In your deploy script:

bash
# After pulling new code
rm -f storage/cache/routes.php
Or:

bash
php bin/compile-routes.php
Where bin/compile-routes.php registers all routes and calls cacheCompiledRoutes().

Dev Workflow
During development, disable the cache:

php
$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (getenv('APP_ENV') !== 'production' || !$router->loadCache()) {
    // register routes
    $router->compile();

    if (getenv('APP_ENV') === 'production') {
        $router->cacheCompiledRoutes();
    }
}
This way:

Dev: always recompiles (no stale cache)

Prod: loads from cache

Cache File Format
The cache file is a PHP file returning a structured array:

php
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
Structure
Key	Contents
static	Static routes grouped by HTTP method
dynamic	Dynamic routes (with regex) grouped by HTTP method
names	Name index for urlFor()
Why This Format?
Plain PHP — fastest possible load (OPcache-friendly)

No unserialize() — safe from object injection

Human-readable — you can inspect it

Common Patterns
Development vs Production
php
$isProd = ($_ENV['APP_ENV'] ?? 'development') === 'production';

$router->enableCache(__DIR__ . '/../storage/cache/routes.php');

if (!$isProd || !$router->loadCache()) {
    require __DIR__ . '/../routes/web.php';

    $router->compile();

    if ($isProd) {
        $router->cacheCompiledRoutes();
    }
}
Cache Per Environment
Different cache files for different environments:

php
$cacheFile = __DIR__ . "/../storage/cache/routes.{$_ENV['APP_ENV']}.php";
$router->enableCache($cacheFile);
Avoids mixing dev and prod routes.

Precompile on Deploy
Create bin/compile-routes.php:

php
<?php

require __DIR__ . '/../vendor/autoload.php';

$router = new Router('https://mysite.com');
require __DIR__ . '/../routes/web.php';

$router->compile();
$router->cacheCompiledRoutes();

echo "Routes compiled to storage/cache/routes.php\n";
Run in your deploy:

bash
php bin/compile-routes.php
Cache Warmup
In your deploy pipeline:

bash
# 1. Pull code
git pull

# 2. Install deps
composer install --no-dev --optimize-autoloader

# 3. Compile routes
php bin/compile-routes.php

# 4. Restart PHP-FPM (optional, for OPcache)
sudo systemctl reload php8.1-fpm
Permission Notes
The cache file needs write permissions during compilation:

bash
chmod -R 775 storage/cache
If you run PHP-FPM as www-data:

bash
chown -R www-data:www-data storage/cache
Common Errors
Cache Not Being Written
Symptom: routes.php doesn't appear in storage/cache/.

Causes:

Directory doesn't exist — the Router creates it with mkdir($dir, 0777, true), but permissions may block

Permission denied — the PHP process can't write

cacheCompiledRoutes() not called — check your code

Fix:

bash
mkdir -p storage/cache
chmod -R 775 storage/cache
Stale Cache After Adding Routes
Symptom: new routes return 404 after deploy.

Cause: the cache file wasn't regenerated.

Fix:

bash
rm storage/cache/routes.php
Or run php bin/compile-routes.php.

Closure Routes Missing After Cache
Symptom: routes defined with closures work in dev but not in prod.

Cause: closures aren't cached. They must be registered on every request, even with the cache enabled.

Fix: register closures outside the if (!$router->loadCache()) block:

php
// Always register closures
$router->get('/health', fn($req, $res) => $res->json(['ok' => true]));

// Register cacheable routes only on cache miss
if (!$router->loadCache()) {
    $router->get('/user/{id}', [UserController::class, 'show']);
    $router->compile();
    $router->cacheCompiledRoutes();
}
Cache Loaded But Routes Don't Match
Symptom: loadCache() returns true, but requests 404.

Cause: you changed projectUrl and the compiled paths don't match.

Fix: delete and recompile.

Invalid Cache File
Symptom: ParseError or require failure on routes.php.

Cause: the file is corrupted (partial write, manual edit).

Fix: delete and recompile.

urlFor() Fails for a Named Route
Symptom: InvalidArgumentException: Named route 'x' not found.

Cause: the route was added but the cache wasn't regenerated.

Fix: clear cache.

See Also
Routes — route registration

Groups — grouped routes (cached like normal routes)

URL Generation — urlFor() uses the name index

← Back to README | 🇵🇹 Português