Routes
← Back to README | 🇵🇹 Português

Routes are the core of the Router. They associate an HTTP method + path with a handler (closure or controller).

Table of Contents
Registering Routes

HTTP Methods

Dynamic Parameters

Wildcard Parameters

Named Routes

Route Attributes

Handler Types

Parameter Syntax Reference

Path Normalization

Route Matching

Common Errors

Registering Routes
Every HTTP verb has its own method on the Router:

php
$router->get($path, $handler, $name = null, $middleware = null);
$router->post($path, $handler, $name = null, $middleware = null);
$router->put($path, $handler, $name = null, $middleware = null);
$router->patch($path, $handler, $name = null, $middleware = null);
$router->delete($path, $handler, $name = null, $middleware = null);
All methods return $this, so they can be chained:

php
$router
    ->get('/about', [SiteController::class, 'about'], name: 'site.about')
    ->post('/contact', [ContactController::class, 'store'], name: 'contact.store')
    ->get('/health', fn($req, $res) => $res->json(['status' => 'ok']));
Signature
Parameter	Type	Required	Description
$path	string	✅	The route path (e.g. /user/{id})
$handler	callable|array	✅	Closure or [Controller::class, 'method']
$name	?string	❌	Route name for URL generation
$middleware	array|string|null	❌	Route-specific middleware
HTTP Methods
Method	Purpose	Example
GET	Retrieve data	$router->get('/user/{id}', [UserController::class, 'show']);
POST	Create data	$router->post('/user', [UserController::class, 'store']);
PUT	Replace data	$router->put('/user/{id}', [UserController::class, 'replace']);
PATCH	Update data	$router->patch('/user/{id}', [UserController::class, 'update']);
DELETE	Delete data	$router->delete('/user/{id}', [UserController::class, 'destroy']);
Method Not Allowed (405)
If a route exists for GET /user but the request is POST /user, the Router returns 405 Method Not Allowed (not 404).

Dynamic Parameters
Use {param} to capture a single URL segment (does not include /).

Basic Example
php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
Request: GET /user/42

Resolved: $id = "42"

Controller:

php
class UserController
{
    public function show(string $id): Response
    {
        return (new Response())->text("User ID: {$id}");
    }
}
Multiple Parameters
php
$router->get('/post/{slug}/comment/{id}', [CommentController::class, 'show']);
Request: GET /post/hello-world/comment/7

Resolved: $slug = "hello-world", $id = "7"

Parameters Don't Match /
php
$router->get('/user/{id}', ...);
URL	Matches?	Why
/user/42	✅	42 is a single segment
/user/42/profile	❌	Two segments
/user/	❌	Empty segment
/user	❌	No segment
Wildcard Parameters
Use {param*} to capture one or more segments (including /).

Basic Example
php
$router->get('/files/{path*}', [FileController::class, 'download'], name: 'files.download');
Request: GET /files/docs/2025/report.pdf

Resolved: $path = ['docs', '2025', 'report.pdf']

Controller:

php
class FileController
{
    public function download(array $path): Response
    {
        $file = implode('/', $path);  // "docs/2025/report.pdf"
        return (new Response())->text("Download: {$file}");
    }
}
Note: wildcard parameters are always returned as arrays of segments.

Combined with Regular Parameters
php
$router->get('/user/{id}/files/{path*}', [FileController::class, 'userFile']);
Request: GET /user/42/files/docs/report.pdf

Resolved: $id = "42", $path = ['docs', 'report.pdf']

Named Routes
Give a route a name to reference it elsewhere (URL generation, redirects, view helpers).

php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
Naming Rules
Use dot notation for hierarchy: user.show, admin.users.index

Must be unique across the application

Case-sensitive

Can contain letters, numbers, ., _, -

Accessing the Name
php
$route = $request->route();
echo $route->getName();  // "user.show"
Using the Name
php
$url = $router->urlFor('user.show', ['id' => 42]);
// → https://mysite.com/user/42
📖 Full URL generation docs →

Naming in Groups
When a route is inside a group with name, the group name is prefixed:

php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    // final name: admin.users.index
});
Route Attributes
Each Route supports arbitrary attributes for metadata.

Setting Attributes
php
$route->setAttribute('context', 'admin');
$route->setAttribute('version', 'v2');
$route->setAttribute('requires_auth', true);
Reading Attributes
php
$route->getAttribute('context');           // "admin"
$route->getAttribute('missing', 'default'); // "default"
$route->hasAttribute('context');           // true
$route->getAttributes();                   // all
Automatic Attributes
The Router sets one attribute automatically:

Attribute	Set by	Values
context	Router::addRoute()	admin, ecommerce, or custom via group(context: ...)
Using Attributes
Common use case — choosing an error view based on context:

php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
📖 Full error handling docs →

Handler Types
Closures
php
$router->get('/health', function ($request, $response, $params) {
    return $response->json(['status' => 'ok']);
});
Arguments: Request $request, Response $response, array $params

Class Methods
php
$router->get('/user/{id}', [UserController::class, 'show'], name: 'user.show');
The Router resolves the controller via the container (or autowiring) and injects parameters by type and name.

Static Methods
php
$router->get('/ping', [HealthController::class, 'ping']);
Signature Comparison
Handler type	Example	DI support
Closure	fn($req, $res) => ...	❌ (only $req, $res, $params)
Class method	[UserController::class, 'show']	✅ (full DI)
Parameter Syntax Reference
Syntax	Name	Captures	Example
{id}	Named	One segment (no /)	/user/{id} → /user/42 → $id = "42"
{path*}	Wildcard	One or more segments	/files/{path*} → /files/a/b → $path = ['a','b']
{id:\d+}	(not supported)	—	—
{id?}	(not supported)	—	—
Supported: named {} and wildcard {*}.
Not supported: regex constraints, optional parameters (use default values in the controller instead).

Path Normalization
The Router normalizes paths during registration:

Input	Normalized
/users	/users
users	/users
/users/	/users
''	/
'/'	/
Group Prefix Application
php
$router->group('/admin', callback: function ($r) {
    $r->get('users', ...);       // → /admin/users
    $r->get('/users', ...);      // → /admin/users
    $r->get('/', ...);           // → /admin
    $r->get('', ...);            // → /admin
});
Route Matching
The Router matches requests in this order:

Static routes — exact path lookup (O(1))

Dynamic routes — regex match (iterates)

Static vs Dynamic
Path	Type	Matched by
/users	Static	Hash lookup
/users/{id}	Dynamic	Regex
Static routes always win when both could match.

Example
php
$router->get('/users/list', [UserController::class, 'list']);   // static
$router->get('/users/{id}', [UserController::class, 'show']);   // dynamic
Request	Matched by
GET /users/list	list (static)
GET /users/42	show (dynamic)
Order Matters for Dynamic Routes
Dynamic routes are matched in registration order. Register specific routes before generic ones:

php
// ✅ Correct order
$router->get('/users/new', [UserController::class, 'create']);
$router->get('/users/{id}', [UserController::class, 'show']);

// ❌ Wrong order — /users/new would match {id}
$router->get('/users/{id}', [UserController::class, 'show']);
$router->get('/users/new', [UserController::class, 'create']);
Common Errors
404 Not Found
The route doesn't exist:

php
$router->get('/users', ...);
// Request: GET /user → 404 (typo: missing 's')
405 Method Not Allowed
The path exists, but the method doesn't:

php
$router->get('/users', ...);
// Request: POST /users → 405
Invalid Handler
php
$router->get('/x', 'not-a-callable');
// ❌ InvalidArgumentException: Handler inválido
Valid handlers:

Closure

[ClassName::class, 'method']

Invalid Controller
php
$router->get('/x', [NonExistentClass::class, 'method']);
// ❌ InvalidArgumentException: Controller inválido
The class must exist at registration time.

Duplicate Routes
If you register the same path + method twice, the first one wins:

php
$router->get('/x', fn() => 'first');
$router->get('/x', fn() => 'second');
// GET /x → "first"
The Router does not warn about duplicates.

Examples
RESTful API
php
$router->get('/posts', [PostController::class, 'index'], name: 'posts.index');
$router->get('/posts/{id}', [PostController::class, 'show'], name: 'posts.show');
$router->post('/posts', [PostController::class, 'store'], name: 'posts.store');
$router->put('/posts/{id}', [PostController::class, 'update'], name: 'posts.update');
$router->delete('/posts/{id}', [PostController::class, 'destroy'], name: 'posts.destroy');
File Download
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
See Also
Groups — grouping routes with prefix, name, context

Middleware — before/after pipeline

Controllers — closures and classes

URL Generation — urlFor()

Error Handling — 404, 405, custom handlers

← Back to README | 🇵🇹 Português