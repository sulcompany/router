<?php
declare(strict_types=1);

namespace SulCompany\Router;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Psr\Container\ContainerInterface;

class Dispatcher
{
    protected Router $router;
    protected ?array $requestData = null;
    protected ?array $nameIndex;
    protected string $httpMethod;
    protected string $path;
    protected ?Route $matchedRoute = null;
    protected ?array $params = null;
    protected ?int $error = null;

    protected Request $request;
    protected Response $response;

    protected ?ContainerInterface $container = null;

    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_IMPLEMENTED = 501;

    public function __construct(Router $router, ?ContainerInterface $container = null)
    {
        $this->router = $router;
        $this->container = $container;
        $this->nameIndex = $router->getNameIndex();

        $this->httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->extractPathFromRequestUri($router->getProjectUrl());
        $this->gatherRequestData();

        $this->request = new Request(
            $_SERVER,
            $_GET ?? [],
            $_POST ?? [],
            $this->requestData['__json'] ?? [],
            $_FILES?? [],
            $this->httpMethod,
            $this->path
        );

        $this->response = new Response();

        $this->ensureCompiled();
    }

    protected function ensureCompiled(): void
    {
        if ($this->router->cacheEnabled && $this->router->loadCache()) return;

        $this->router->compile();

        if ($this->router->cacheEnabled) {
            $this->router->cacheCompiledRoutes();
        }
    }

    protected function extractPathFromRequestUri(string $projectUrl): string
    {
        $basePath = parse_url($projectUrl, PHP_URL_PATH) ?: '/';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (str_starts_with($requestUri, $basePath)) {
            $routePath = substr($requestUri, strlen($basePath));
        } else {
            $routePath = $requestUri;
        }

        $routePath = '/' . ltrim($routePath, '/');
        return rtrim($routePath, '/') ?: '/';
    }


    protected function gatherRequestData(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $get = $_GET ?? [];
        $post = $_POST ?? [];
        $json = [];

        // Se for PUT, PATCH ou DELETE precisamos ler manualmente
        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {

            $raw = file_get_contents('php://input');

            if (stripos($contentType, 'application/json') !== false) {
                $json = json_decode($raw, true) ?? [];
            } else {
                parse_str($raw, $post);
            }
        }

        // suporte a _method override
        if (isset($post['_method'])) {
            $method = strtoupper($post['_method']);
            unset($post['_method']);
        }

        $this->httpMethod = strtoupper($method);
        $this->requestData = array_merge($get, $post);
        $this->requestData['__json'] = $json;
    }

    
    public function dispatch(): bool
    {
        $method = $this->httpMethod;

        // Static routes
        $static = $this->router->getStaticRoutes()[$method] ?? [];
        if (isset($static[$this->path])) {
            $this->matchedRoute = $static[$this->path];
            $this->params = [];
            $this->request->setRoute($this->matchedRoute);
            return $this->runMatched();
        }

        // Dynamic routes
        $dynamic = $this->router->getDynamicRoutes()[$method] ?? [];
        foreach ($dynamic as $entry) {
            if (preg_match($entry['regex'], $this->path, $matches)) {
                array_shift($matches);
                $route = $entry['route'];
                $params = [];
                foreach ($route->paramNames as $i => $name) {
                    $params[str_ends_with($name, '*') ? rtrim($name, '*') : $name] =
                        str_ends_with($name, '*') ? explode('/', $matches[$i] ?? '') : ($matches[$i] ?? null);
                }
                $this->matchedRoute = $route;
                $this->params = $params;
                $this->request->setRoute($route);
                return $this->runMatched();
            }
        }

        $this->error = self::NOT_FOUND;
        return false;
    }

    public function error(): ?int
    {
        return $this->error;
    }

    /**
     * Executa middlewares modernos e controller
     */
    protected function runMatched(): bool
    {
        if (!$this->matchedRoute) {
            $this->error = self::NOT_FOUND;
            return false;
        }

        $executedPipeline = [];

        // ---- BEFORE MIDDLEWARES ----
        foreach ($this->matchedRoute->middlewares as $mwClass) {
            $middleware = $this->resolveClass($mwClass);
            $executedPipeline[] = $middleware;

            if (method_exists($middleware, 'before')) {
                if ($middleware->before($this->request, $this->response) === false) {
                    return false; // interrompe pipeline
                }
                continue;
            }

            $this->error = self::METHOD_NOT_ALLOWED;
            return false;
        }

        // ---- CONTROLLER ----
        if (!$this->runController()) {
            return false;
        }

        // ---- AFTER MIDDLEWARES ----
        foreach (array_reverse($executedPipeline) as $middleware) {
            if (method_exists($middleware, 'after')) {
                $middleware->after($this->request, $this->response);
            }
        }

        return true;
    }

    protected function runController(): bool
    {
        $route = $this->matchedRoute;

        // Closure
        if (is_callable($route->handler) && !$this->isStringCallable($route->handler)) {
            call_user_func($route->handler, $this->request, $this->response, $this->params);
            return true;
        }

        // Controller
        $controllerClass = $route->handler;
        $method = $route->action;

        if (!class_exists($controllerClass)) {
            $this->error = self::BAD_REQUEST;
            return false;
        }

        $controller = $this->resolveClass($controllerClass);

        if (!method_exists($controller, $method)) {
            $this->error = self::METHOD_NOT_ALLOWED;
            return false;
        }

        $reflection = new ReflectionMethod($controller, $method);
        $args = [];
        foreach ($reflection->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }

        $reflection->invokeArgs($controller, $args);
        return true;
    }

    /**
     * Resolve uma classe usando container ou reflection
     */
    protected function resolveClass(string $class)
    {
        // 1. Tenta container
        if ($this->container && $this->container->has($class)) {
            return $this->container->get($class);
        }

        // 2. Reflection
        $r = new ReflectionClass($class);
        $constructor = $r->getConstructor();
        if (!$constructor) return new $class();

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }

        return $r->newInstanceArgs($args);
    }

    /**
     * Resolve parâmetro de método ou construtor
     */
    protected function resolveParameter(\ReflectionParameter $param)
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            // Request e Response
            if ($name === Request::class) return $this->request;
            if ($name === Response::class) return $this->response;

            // Container / auto-instancia
            if ($this->container && $this->container->has($name)) {
                return $this->container->get($name);
            }
            if (class_exists($name)) {
                return $this->resolveClass($name);
            }
        }

        // Parâmetro da rota
        $pName = $param->getName();
        if (isset($this->params[$pName])) return $this->params[$pName];

        // Query / POST / JSON
        return $this->request->all($pName);
    }

    protected function isStringCallable($h): bool
    {
        return is_string($h) && strpos($h, '::') !== false;
    }
}
