<?php
declare(strict_types=1);

namespace SulCompany\Router;

class Dispatcher
{
    protected Router $router;
    protected ?array $requestData = null;
    protected string $httpMethod;
    protected string $path;
    protected ?Route $matchedRoute = null;
    protected ?array $params = null;
    protected ?int $error = null;

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
        $this->ensureCompiled();
    }

    protected function ensureCompiled(): void
    {
        if ($this->router->cacheEnabled && $this->router->loadCache()) {
            return;
        }
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
        $routePath = rtrim($routePath, '/');
        return $routePath === '' ? '/' : $routePath;
    }

    protected function gatherRequestData(): void
    {
        $post = filter_input_array(INPUT_POST, FILTER_DEFAULT) ?? [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $json = [];
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $json = json_decode($raw, true) ?? [];
        }
        $get = filter_input_array(INPUT_GET, FILTER_DEFAULT) ?? [];
        $method = strtoupper($post['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        unset($post['_method']);
        $this->requestData = array_merge($get, $post, $json);
        $this->httpMethod = $method;
    }

    public function dispatch(): bool
    {
        $method = $this->httpMethod;

        $static = $this->router->getStaticRoutes()[$method] ?? [];
        if (isset($static[$this->path])) {
            $this->matchedRoute = $static[$this->path];
            $this->params = [];
            return $this->runMatched();
        }

        $dynamic = $this->router->getDynamicRoutes()[$method] ?? [];
        foreach ($dynamic as $entry) {
            if (preg_match($entry['regex'], $this->path, $matches)) {
                array_shift($matches);
                $route = $entry['route'];
                $paramNames = $route->paramNames ?? [];
                $params = [];
                foreach ($paramNames as $index => $name) {
                    if (str_ends_with($name, '*')) {
                        $params[rtrim($name, '*')] = explode('/', $matches[$index] ?? '');
                    } else {
                        $params[$name] = $matches[$index] ?? null;
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

    protected function runMatched(): bool
    {
        if (!$this->matchedRoute) {
            $this->error = self::NOT_FOUND;
            return false;
        }

        foreach ($this->matchedRoute->middlewares as $mwClass) {
            if (!class_exists($mwClass)) {
                $this->error = self::NOT_IMPLEMENTED;
                return false;
            }
            $instance = new $mwClass();
            if (!method_exists($instance, 'handle')) {
                $this->error = self::METHOD_NOT_ALLOWED;
                return false;
            }
            if (!$instance->handle($this)) {
                return false;
            }
        }

        $route = $this->matchedRoute;

        if (is_callable($route->handler) && !$this->isStringCallable($route->handler)) {
            call_user_func($route->handler, $this->requestData ?? [], $this);
            return true;
        }

        if (!is_string($route->handler) || $route->action === null) {
            $this->error = self::BAD_REQUEST;
            return false;
        }

        if (!class_exists($route->handler)) {
            $this->error = self::BAD_REQUEST;
            return false;
        }

        $instance = new $route->handler($this);
        if (!method_exists($instance, $route->action)) {
            $this->error = self::METHOD_NOT_ALLOWED;
            return false;
        }

        $instance->{$route->action}($this->requestData ?? [], $this->params ?? []);
        return true;
    }

    protected function isStringCallable($h): bool
    {
        return is_string($h) && strpos($h, '::') !== false;
    }

    // -- helpers for controllers/middleware --
    public function data(): ?array
    {
        return $this->requestData;
    }

    public function params(): ?array
    {
        return $this->params;
    }

    public function route(): ?Route
    {
        return $this->matchedRoute;
    }

    public function error(): ?int
    {
        return $this->error;
    }
}
