<?php

require __DIR__ . '/vendor/autoload.php';

use SulCompany\Router\Router;

$route = new Router('http://localhost/router/Example', '@'); 

    $route->namespace("Example\Http\Controllers"); 

    $route->get("/", "HomeController@index");
    $route->post("/create", "HomeController@store");

    // Rota com closure - evitar cache ou não usar cache
    $route->get("/user/{id}", function ($data) {
        echo "User ID: " . $data['id'];
    });

    $route->get("/dashboard", "HomeController@index", name: "dashboard", middleware: "Example\Middlewares\AuthMiddleware");

    $route->group("/produto", function($router) { 
        $router->get("/nome/mateus", "ProductController@index");
        $router->get("/{slug}/{params*}", "ProductController:index");
    });


    $route->namespace("Example\Http\Controllers\Admin"); 
    $route->group('/admin', function($router) {
        $router->get("", "DashController@index");
        $router->get("/home", "DashController@index");
    });

$route->dispatch();


// Mostrar erro se houver
if ($route->error()) {
    echo "Erro: " . $route->error();
}

