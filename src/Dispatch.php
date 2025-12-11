<?php

namespace SulCompany\Router;

abstract class Dispatch
{
    use RouterTrait;

    protected string $projectUrl;
    protected string $httpMethod;
    protected string $path;
    protected ?array $route = null;
    protected array $routes = [];
    protected string $separator;
    protected ?string $group = null;
    protected ?array $middleware = null;
    protected ?array $data = null;
    protected ?int $error = null;

    /** @var array Middlewares globais */
    protected array $globalMiddlewares = [];

    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_IMPLEMENTED = 501;

    public function __construct(string $projectUrl, ?string $separator = ":")
    {
        $this->projectUrl = rtrim($projectUrl, "/");
        $this->separator = $separator ?? ":";
        $this->httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->extractPathFromRequestUri();
    }

    private function extractPathFromRequestUri(): string
    {
        $basePath = parse_url($this->projectUrl, PHP_URL_PATH) ?: '/';
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

        if (str_starts_with($requestUri, $basePath)) {
            $routePath = substr($requestUri, strlen($basePath));
        } else {
            $routePath = $requestUri;
        }

        $routePath = '/' . ltrim($routePath, '/');
        $routePath = rtrim($routePath, '/');

        return $routePath === '' ? '/' : $routePath;
    }

    public function dispatch(): bool
    {
        if (empty($this->routes[$this->httpMethod])) {
            $this->error = self::NOT_IMPLEMENTED;
            return false;
        }

        foreach ($this->routes[$this->httpMethod] as $regex => $route) {
            if (preg_match($regex, $this->path, $matches)) {
                array_shift($matches);

                $paramNames = $route['__paramNames'] ?? [];
                $routeParams = [];

                foreach ($paramNames as $index => $name) {
                    if (str_ends_with($name, '*')) {
                        $routeParams[rtrim($name, '*')] = explode('/', $matches[$index]);
                    } else {
                        $routeParams[$name] = $matches[$index] ?? null;
                    }
                }

                $this->data = array_merge($this->data ?? [], $routeParams);
                $this->route = $route;
                $this->route['data'] = $this->data;

                return $this->execute();
            }
        }

        $this->error = self::NOT_FOUND;
        return false;
    }

    public function group(string $prefix, callable $callback, array|string $middleware = null): self
    {
        $previousGroup = $this->group;
        $previousMiddleware = $this->middleware;

        $this->group = trim($prefix, "/");
        $this->middleware = $middleware ? [$this->group => (array)$middleware] : null;

        $callback($this);

        $this->group = $previousGroup;
        $this->middleware = $previousMiddleware;

        return $this;
    }

    public function data(): ?array
    {
        return $this->data;
    }

    public function current(): ?object
    {
        return (object) array_merge(
            [
                "group" => $this->group,
                "path" => $this->path
            ],
            $this->route ?? []
        );
    }

    public function error(): ?int
    {
        return $this->error;
    }

    public function addGlobalMiddleware(array|string $middleware): self
    {
        if (is_string($middleware)) $middleware = [$middleware];
        $this->globalMiddlewares = array_merge($this->globalMiddlewares ?? [], $middleware);
        return $this;
    }

    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }
}
