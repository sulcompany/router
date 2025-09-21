# Router @SulCompany 

> US English below | PT leia em português abaixo

A simple, flexible, and dependency-free router with support for:

###### Um roteador simples, flexível e sem dependências, com suporte a:

- REST routes - Rotas REST (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`)
- Named parameters - Parâmetros nomeados (`/user/{id}`)
- Dynamic multi-parameters - Parâmetros múltiplos dinâmicos (`/produto/{params*}`)
- middlewares (`/route`, `Controller:action`, `Example\Middlewares\AuthMiddleware`)
- Route groups - Grupo de rotas 
- Route caching for performance optimization - Cache de rotas para melhorar performance
- Route - rota "clean URLs"

---

## 🚀 Installation | Instalação

Router is available via Composer - Router está disponível via composer:

```bash
composer require sulcompany/router
```

## Documentation | Documentação

For usage details, check the example folder in the component directory.
Make sure all navigation is redirected to the main routing file (index.php), where all traffic is handled.
See the example below:

###### Para ver como usar o router, consulte a pasta de exemplo no diretório do componente. Certifique-se de redirecionar a navegação para o arquivo principal de rotas (index.php), onde todo o tráfego é tratado. Veja o exemplo abaixo:

#### Apache

```apacheconfig
RewriteEngine On

#Options All -Indexes

## ROUTER WWW Redirect.
#RewriteCond %{HTTP_HOST} !^www\. [NC]
#RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

## ROUTER HTTPS Redirect
#RewriteCond %{HTTP:X-Forwarded-Proto} !https
#RewriteCond %{HTTPS} off
#RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ROUTER URL Rewrite
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```


##### Basic usage | exemplo básico de uso

```php
<?php 

use SulCompany\Router\Router;

$router = new Router("https://www.youdomain.com", "@");

$route->namespace("App\Http\Controllers"); 

$router->get("/about", "Controller@method");
$router->post("/user/regist", "Controller@method");
$router->put("/user/{id}/profile", "Controller@method");
$router->patch("/user/{id}/profile/{photo}", "Controller@method");
$router->delete("/user/{id}", "Controller:method");

$router->namespace("App\Http\Controllers\Admin");
$router->group('admin', function($router) { 
        $router->get('/', 'DashController@index');
    }, middleware: 'App\Http\Middlewares\AuthAdminMiddleware'
);

$router->dispatch();

if ($router->error()) {
    echo $router->error();
}
```


##### Basic usage with Route Caching | Exemplo básico de uso com cacheamento de rotas

```php
<?php 

use SulCompany\Router\Router;

$router = new Router("https://www.youdomain.com", "@");

$route->enableCache(__DIR__ . '/app/cache/router.cache.php');

if (!$route->loadCache()) {
    $route->namespace("App\Http\Controllers"); 

    $router->get("/about", "Controller@method");
    $router->post("/user/regist", "Controller@method");
    /**
     * Others routes
    */

    $route->cacheRoutesIfEnabled();
}

$router->dispatch();

if ($router->error()) {
    echo $router->error();
}
```

##### Routes

```php
<?php 

use SulCompany\Router\Router;

/**
 * You can use @, : or any custom separator to define the method-controller 
 * relationship in route definitions
 * 
 * Use @ , : ou qualquer separador para definir o controller-metodo ao definir rotas
**/
$router = new Router("https://www.youdomain.com", "@");

/**
 * routes
 * The controller must be in the namespace Test\Controller
 * O controlador deve estar no namespace App\Http\Controller.
 */
$route->namespace("App\Http\Controllers"); 

$router->get("/about", "Controller@method");
$router->post("/user/regist", "Controller@method");
$router->put("/user/{id}/profile", "Controller@method");
$router->patch("/user/{id}/profile/{photo}", "Controller@method");
$router->delete("/user/{id}", "Controller:method");



/**
 * group by routes and namespace | grupo de routas e namespace
 * The controller must be in the namespace App\Http\Controllers\Admin
 */
$route->namespace("App\Http\Controllers\Admin");
$route->group('admin', function($router) { 
        $router->get('/', 'DashController@index');
        $router->get('/user/edit/{id}', 'DashController@index');
    }, middleware: 'App\Http\Middlewares\AuthInventoryMiddleware'
);


/**
 * This method executes the routes |  este metodo executa as rotas
 */
$router->dispatch();

if ($router->error()) {
    echo $router->error();
}
```

##### Named params | Parâmetros nomedados

```php
<?php 

use SulCompany\Router\Router;

$router = new Router("https://www.youdomain.com", "@");

$route->namespace("App\Http\Controllers"); 

$router->get("/user/{id}", "Controller@method");

/**
 *{id} is a named parameter. You can define a route like domain/user/248 
 *and retrieve the parameter like this: $id = $data['id']
 *{id} e um parametro nomeado e. Defina uma routa assim: dominio/user/248
 * e podes receber o parametro deste geito $id = $data['id'];
*/

$router->dispatch();

if ($router->error()) {
    echo $router->error();
}
```

## Credits

- [Francisco Dulo](https://github.com/jobpires14) (Developer)
- [Sul Company Lda](https://github.com/sulcompany) (Team)
- [All Contributors](https://github.com/sulcompany/router/contributors) (This Rock)


## License

The MIT License (MIT). Please see [License File](https://github.com/sulcompany/router/blob/main/LICENSE) for more
information.
