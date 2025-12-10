<?php

require __DIR__ . '/vendor/autoload.php';

use SulCompany\Router\Router;

$route = new Router('http://localhost/router/Example');

// Ativa o cache
//$route->enableCache(__DIR__ . '/app/cache/router.cache.php');

//if (!$route->loadCache()) {
    // Define namespace, rotas, etc.

    $route->namespace("Example\Http\Controllers"); 

    $route->get("/", "HomeController:index");
    $route->get("/user/{id}", "HomeController:index");

    // Rota com closure - evitar cache ou não usar cache
    /*$route->get("/user/{id}", function ($data) {
        echo "User ID: " . $data['id'];
    });*/

    $route->get("/dashboard", "HomeController:index", name: "dashboard", middleware: "Example\Middlewares\AuthMiddleware");

    $route->group("/produto", function($router) { 
        $router->get("/nome/mateus", "ProductController:index");
        $router->get("/{slug}/{params*}", "ProductController:index");
    });

    // Salva cache
    //$route->cacheRoutesIfEnabled();
//}

// Dispara a rota
$route->dispatch();

// Mostrar erro se houver
if ($route->error()) {
    echo "Erro: " . $route->error();
}

