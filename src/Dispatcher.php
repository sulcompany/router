<?php

declare(strict_types=1);

namespace SulCompany\Router;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class Dispatcher
{
    protected Router $router;
    protected ?array $requestData = null;
    protected string $httpMethod;
    protected string $path;
    protected ?Route $matchedRoute = null;
    protected ?array $params = null;
    protected ?int $error = null;

    protected Request $request;
    protected Response $response;

    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_IMPLEMENTED = 501;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->extractPathFromRequestUri($router->getProjectUrl());
        $this->gatherRequestData();

        $this->request = new Request(
            $_GET ?? [],
            $_POST ?? [],
            $this->requestData['__json'] ?? [],
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
        } else $routePath = $requestUri;

        $routePath = '/' . ltrim($routePath, '/');
        return rtrim($routePath, '/') ?: '/';
    }

    protected function gatherRequestData(): void
    {
        $post = filter_input_array(INPUT_POST, FILTER_DEFAULT) ?? [];
        $get = filter_input_array(INPUT_GET, FILTER_DEFAULT) ?? [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $json = [];
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true) ?? [];
        }

        $method = strtoupper($post['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        unset($post['_method']);

        $this->httpMethod = $method;

        $this->requestData = array_merge($get, $post);
        $this->requestData['__json'] = $json;
    }

    public function dispatch(): bool
    {
        $method = $this->httpMethod;

        // Static
        $static = $this->router->getStaticRoutes()[$method] ?? [];
        if (isset($static[$this->path])) {
            $this->matchedRoute = $static[$this->path];
            $this->params = [];
            return $this->runMatched();
        }

        // Dynamic
        $dynamic = $this->router->getDynamicRoutes()[$method] ?? [];
        foreach ($dynamic as $entry) {
            if (preg_match($entry['regex'], $this->path, $matches)) {
                array_shift($matches);
                $route = $entry['route'];
                $params = [];

                foreach ($route->paramNames as $i => $name) {
                    if (str_ends_with($name, '*')) {
                        $params[rtrim($name, '*')] = explode('/', $matches[$i] ?? '');
                    } else {
                        $params[$name] = $matches[$i] ?? null;
                    }
                }

                $this->matchedRoute = $route;
                $this->params = $params;
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

    protected function runMatched(): bool
    {
        if (!$this->matchedRoute) {
            $this->error = self::NOT_FOUND;
            return false;
        }

        // Run middlewares
        foreach ($this->matchedRoute->middlewares as $mwClass) {
            $mw = new $mwClass();
            if (!method_exists($mw, 'handle')) {
                $this->error = self::METHOD_NOT_ALLOWED;
                return false;
            }
            if (!$mw->handle($this->request, $this->response)) {
                return false;
            }
        }

        return $this->runController();
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

    protected function resolveClass(string $class)
    {
        $r = new ReflectionClass($class);

        $constructor = $r->getConstructor();
        if (!$constructor) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }

        return $r->newInstanceArgs($args);
    }

    protected function resolveParameter(\ReflectionParameter $param)
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if ($name === Request::class) return $this->request;
            if ($name === Response::class) return $this->response;

            // Try to resolve service (auto-instantiation)
            if (class_exists($name)) {
                return $this->resolveClass($name);
            }
        }

        // Try route param
        $pName = $param->getName();
        if (isset($this->params[$pName])) {
            return $this->params[$pName];
        }

        // Try body/query/all
        return $this->request->all($pName);
    }

    protected function isStringCallable($h): bool
    {
        return is_string($h) && strpos($h, '::') !== false;
    }
}
