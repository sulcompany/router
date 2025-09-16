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
    protected ?string $namespace = null;
    protected ?string $group = null;
    protected ?array $middleware = null;
    protected ?array $data = null;
    protected ?int $error = null;

    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_IMPLEMENTED = 501;

    public function __construct(string $projectUrl, ?string $separator = ":")
    {
        $this->projectUrl = rtrim($projectUrl, "/");
        $this->separator = $separator ?? ":";
        $this->httpMethod = $_SERVER['REQUEST_METHOD'];

        // Extrai a rota limpa a partir do REQUEST_URI e da base da aplicação
        $this->path = $this->extractPathFromRequestUri();
    }


    private function extractPathFromRequestUri(): string
    {
        // Extrai o path base do projectUrl (ex: /router/example)
        $basePath = parse_url($this->projectUrl, PHP_URL_PATH) ?: '/';

        // Extrai o path da requisição atual (ex: /router/example/test)
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

        // Remove o basePath do início do requestUri
        if (str_starts_with($requestUri, $basePath)) {
            $routePath = substr($requestUri, strlen($basePath));
        } else {
            // Se não bate, assume toda a requestUri
            $routePath = $requestUri;
        }

        // Normaliza a rota, garantindo barra inicial e sem barra no fim
        $routePath = '/' . ltrim($routePath, '/');
        $routePath = rtrim($routePath, '/');

        // Raiz da aplicação = "/"
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
                foreach ($paramNames as $index => $name) {
                    if (str_ends_with($name, '*')) {
                        $this->data[rtrim($name, '*')] = explode('/', $matches[$index]);
                    } else {
                        $this->data[$name] = $matches[$index] ?? null;
                    }
                }

                $this->route = $route;
                $this->route['data'] = $this->data;
                return $this->execute();
            }
        }

        $this->error = self::NOT_FOUND;
        return false;
    }

    public function namespace(?string $namespace): Dispatch
    {
        $this->namespace = ($namespace ? ucwords($namespace) : null);
        return $this;
    }

    
    public function group(string $prefix, callable $callback, array|string $middleware = null): self
    {
        $previousGroup = $this->group;
        $previousMiddleware = $this->middleware;

        $this->group = trim($prefix, "/");
        $this->middleware = $middleware ? [$this->group => $middleware] : null;

        $callback($this);

        // Reset após o callback
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
        return (object)array_merge(
            [
                "namespace" => $this->namespace,
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
}
