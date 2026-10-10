Groups
← Back to README | 🇵🇹 Português

Groups let you organize routes with a shared prefix, name prefix, context, and middleware. They can be nested, and every property is inherited by child routes.

Table of Contents
Signature

Basic Group

Group with Name

Group with Context

Group with Middleware

Full Example

Nested Groups

Inheritance Rules

Prefix Normalization

Common Patterns

Common Errors

Signature
php
public function group(
    string $prefix,
    callable $callback,
    array|string|null $middleware = null,
    ?string $name = null,
    ?string $context = null
): self;
Parameter	Type	Required	Description
$prefix	string	✅	URL prefix (e.g. /admin)
$callback	callable	✅	Callback that receives the Router
$middleware	array|string|null	❌	Group middleware
$name	?string	❌	Name prefix (e.g. admin)
$context	?string	❌	Context attribute (e.g. admin)
Named Arguments (Recommended)
Because the parameter list is long, use named arguments for clarity:

php
$router->group(
    prefix: '/admin',
    name: 'admin',
    context: 'admin',
    middleware: [AuthAdminMiddleware::class],
    callback: function ($r) {
        // routes here
    }
);
Basic Group
Only a prefix:

php
$router->group('/admin', function ($r) {
    $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
Resulting routes:

Final path	Final name	Context
/admin/dashboard	dashboard	ecommerce (default)
/admin/users	users.index	ecommerce (default)
Note: the name is not prefixed unless you pass name.

Group with Name
Add name to prefix all route names inside:

php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
Resulting route:

Final path	Final name
/admin/users	admin.users.index
Why Name Prefixing Matters
URL generation uses the final name:

php
$router->urlFor('admin.users.index');
// → https://mysite.com/admin/users
Nested Name Prefixes
php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
        // final name: admin.users.list
    });
});
Group with Context
Add context to tag all routes inside. This attribute is read by the error handler to choose the right error view.

php
$router->group('/admin', name: 'admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
Resulting route:

Final path	Final name	Context
/admin/users	admin.users.index	admin
Default Context
If no context is declared anywhere in the chain, Router::DEFAULT_CONTEXT is used (ecommerce).

Reading the Context
php
$context = $request->route()?->getAttribute('context') ?? 'ecommerce';
Example: Error Views
php
final class AppErrorHandler implements ErrorHandlerInterface
{
    public function handle(int $status, Request $request, Response $response): Response
    {
        $context = $request->route()?->getAttribute('context') ?? 'ecommerce';
        $view = "{$context}/errors/{$status}";

        return $response->html($this->view->render($view, ['status' => $status]), $status);
    }
}
📖 Full error handling docs →

Group with Middleware
Add middleware to run for every route in the group:

php
$router->group(
    '/admin',
    middleware: [AuthAdminMiddleware::class, CsrfMiddleware::class],
    callback: function ($r) {
        $r->get('/dashboard', [AdminController::class, 'dashboard']);
    }
);
Multiple Middleware
Pass an array to apply in order:

php
$router->group('/admin', middleware: [
    LogRequestsMiddleware::class,
    AuthAdminMiddleware::class,
    CsrfMiddleware::class,
], callback: function ($r) {
    // ...
});
Execution order:

Global middleware

Group middleware (in array order)

Route middleware

📖 Full middleware docs →

Full Example
Combine everything:

php
$router->group(
    prefix: '/admin',
    name: 'admin',
    context: 'admin',
    middleware: [AuthAdminMiddleware::class],
    callback: function ($r) {
        $r->get('/dashboard', [AdminController::class, 'dashboard'], name: 'dashboard');
        $r->get('/users', [UserController::class, 'index'], name: 'users.index');
        $r->get('/settings', [SettingsController::class, 'index'], name: 'settings.index');
    }
);
Resulting routes:

Path	Name	Context	Middleware
/admin/dashboard	admin.dashboard	admin	global + AuthAdminMiddleware
/admin/users	admin.users.index	admin	global + AuthAdminMiddleware
/admin/settings	admin.settings.index	admin	global + AuthAdminMiddleware
Nested Groups
Groups can be nested to any depth. Prefixes, names, middleware, and context cascade down.

php
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->group('/users', name: 'users', callback: function ($r2) {
        $r2->get('/list', [UserController::class, 'index'], name: 'list');
        $r2->get('/{id}', [UserController::class, 'show'], name: 'show');
        $r2->group('/roles', name: 'roles', callback: function ($r3) {
            $r3->get('/', [RoleController::class, 'index'], name: 'index');
        });
    });
});
Resulting routes:

Path	Name	Context
/admin/users/list	admin.users.list	admin
/admin/users/{id}	admin.users.show	admin
/admin/users/roles	admin.users.roles.index	admin
Middleware: AuthAdmin::class is applied to all nested routes.

Inheritance Rules
Prefix
Concatenated with the parent prefix:

text
parent: /admin
child:  /users
final:  /admin/users
Name
Concatenated with a . separator:

text
parent: admin
child:  users
final:  admin.users
Middleware
Merged (parent first, then child):

text
parent: [AuthAdmin]
child:  [Csrf]
final:  [AuthAdmin, Csrf]
Context
Inherited unless the child overrides:

text
parent: admin
child:  (none)      → admin   (inherited)
child:  ecommerce   → ecommerce (overridden)
Full Inheritance Table
Property	Inheritance
prefix	Concatenated
name	Concatenated with .
middleware	Merged (parent first)
context	Inherited, overridable
Prefix Normalization
The Router normalizes prefixes during registration:

Input	Resulting prefix
/admin	/admin
admin	/admin
admin/	/admin
/admin/	/admin
''	'' (empty)
Prefix with Route Path
Group prefix	Route path	Final path
/admin	/users	/admin/users
/admin	users	/admin/users
/admin	/	/admin
/admin	''	/admin
'' (empty)	/users	/users
Common Patterns
Ecommerce vs Admin
php
// Ecommerce — root, no context (falls back to DEFAULT_CONTEXT)
$router->get('/cart', [CartController::class, 'index'], name: 'ecommerce.cart.index');
$router->get('/checkout', [CheckoutController::class, 'index'], name: 'ecommerce.checkout');

// Admin — explicit context
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
});
API with Versioning
php
$router->group('/api/v1', name: 'api.v1', context: 'api', middleware: [ApiKeyMiddleware::class], callback: function ($r) {
    $r->get('/users', [ApiUserController::class, 'index'], name: 'users.index');
});
Account (sub-area of Ecommerce)
php
$router->group('/account', name: 'account', callback: function ($r) {
    // context: ecommerce (inherited from root)
    $r->get('/profile', [AccountController::class, 'profile'], name: 'profile');
    $r->get('/orders', [AccountController::class, 'orders'], name: 'orders');
});
Backoffice vs Front-office
php
// Front-office (no prefix, no context)
$router->get('/', [HomeController::class, 'index'], name: 'home');

// Backoffice (prefixed + context)
$router->group('/backoffice', name: 'backoffice', context: 'backoffice', callback: function ($r) {
    // ...
});
Common Errors
Context Not Applied to Route
Symptom: the error view of a route is always ecommerce, even for /admin/*.

Cause: the route is not inside a group with context: 'admin'.

Fix:

php
$router->group('/admin', context: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index']);
});
Name Not Prefixed
Symptom: urlFor('users.index') fails for a route inside /admin.

Cause: you forgot name: 'admin' on the group.

Fix:

php
$router->group('/admin', name: 'admin', callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    // final name: admin.users.index
});
Middleware Not Running
Symptom: a global-like middleware isn't applied to a group.

Cause: middleware isn't registered on the group.

Fix:

php
$router->group('/admin', middleware: [AuthAdmin::class], callback: function ($r) {
    // ...
});
Double Slash in Path
Symptom: route path becomes /admin//users.

Cause: prefix and route path both end/start with /.

Fix: The Router normalizes both — but if you use raw strings, ensure no double slashes. The Router handles this automatically.

Examples
Full Ecommerce + Admin Setup
php
// ── Ecommerce (root) ────────────────────────────────
$router->get('/', [HomeController::class, 'index'], name: 'ecommerce.home');
$router->get('/cart', [CartController::class, 'index'], name: 'ecommerce.cart.index');

$router->group('/account', name: 'ecommerce.account', callback: function ($r) {
    $r->get('/profile', [AccountController::class, 'profile'], name: 'profile');
    $r->get('/settings', [AccountController::class, 'settings'], name: 'settings');
});

// ── Admin ────────────────────────────────────────────
$router->group('/admin', name: 'admin', context: 'admin', middleware: [AuthAdmin::class], callback: function ($r) {
    $r->get('/users', [UserController::class, 'index'], name: 'users.index');
    $r->get('/roles', [RoleController::class, 'index'], name: 'roles.index');

    $r->group('/settings', name: 'settings', callback: function ($r2) {
        $r2->get('/general', [SettingsController::class, 'general'], name: 'general');
    });
});
Resulting Name Index
Route	Name
/	ecommerce.home
/cart	ecommerce.cart.index
/account/profile	ecommerce.account.profile
/admin/users	admin.users.index
/admin/roles	admin.roles.index
/admin/settings/general	admin.settings.general
See Also
Routes — registering routes

Middleware — before/after pipeline

Error Handling — using context in error views

URL Generation — urlFor() with named routes

← Back to README | 🇵🇹 Português