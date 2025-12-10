<?php

namespace SulCompany\Router;

class Router extends Dispatch
{
    protected bool $cacheEnabled = false;
    protected ?string $cacheFile = null;

    /** @var array $globalMiddlewares */
    protected array $globalMiddlewares = [];

    public function __construct(string $projectUrl, ?string $separator = ":")
    {
        parent::__construct($projectUrl, $separator);
    }

    /**
     * Adiciona middlewares globais
     */
    public function addGlobalMiddleware(array|string $middleware): Dispatch
    {
        $middlewareArray = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddlewares = array_merge($this->globalMiddlewares, $middlewareArray);
        return $this;
    }


    /**
     * Retorna middlewares globais
     */
    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    /**
     * Ativa o cache de rotas e define o arquivo
     */
    public function enableCache(string $cacheFile): self
    {
        $this->cacheFile = $cacheFile;
        $this->cacheEnabled = true;
        return $this;
    }

    /**
     * Retorna todas as rotas
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Salva cache das rotas (ignorando closures)
     */
    public function cacheRoutesIfEnabled(): void
    {
        if (!$this->cacheEnabled || empty($this->routes)) return;

        $routesToCache = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $regex => $route) {
                if (is_callable($route['handler']) && !is_string($route['handler'])) continue;
                $routesToCache[$method][$regex] = $route;
            }
        }

        $export = var_export($routesToCache, true);
        $cacheContent = "<?php\n\nreturn {$export};\n";

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        file_put_contents($this->cacheFile, $cacheContent);
    }

    /**
     * Carrega rotas do cache
     */
    public function loadCache(): bool
    {
        if ($this->cacheEnabled && $this->cacheFile && file_exists($this->cacheFile)) {
            $cachedRoutes = require $this->cacheFile;

            if (is_array($cachedRoutes)) {
                $this->routes = $cachedRoutes;
                return true;
            }
        }
        return false;
    }

    // MÉTODOS HTTP

    public function get(string $route, callable|array|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("GET", $route, $handler, $name, $middleware);
    }

    public function post(string $route, callable|array|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("POST", $route, $handler, $name, $middleware);
    }

    public function put(string $route, callable|array|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("PUT", $route, $handler, $name, $middleware);
    }

    public function patch(string $route, callable|array|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("PATCH", $route, $handler, $name, $middleware);
    }

    public function delete(string $route, callable|array|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("DELETE", $route, $handler, $name, $middleware);
    }
}
