<?php

namespace SulCompany\Router;

class Router extends Dispatch
{
    // Nova propriedade para controlar se o cache está ativo
    protected bool $cacheEnabled = false;
    
    // Caminho para o arquivo de cache
    protected ?string $cacheFile = null;

    public function __construct(string $projectUrl, ?string $separator = ":")
    {
        parent::__construct($projectUrl, $separator);
    }

    /**
     * Ativa o cache e define o arquivo para salvar o cache
     */
    public function enableCache(string $cacheFile): self
    {
        $this->cacheFile = $cacheFile;
        $this->cacheEnabled = true;
        return $this;
    }

    /**
     * Retorna as rotas carregadas
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Salva o cache das rotas se cache estiver ativo
     */
    public function cacheRoutesIfEnabled(): void
    {
        if (!$this->cacheEnabled || empty($this->routes)) {
            return;
        }

        // Aqui você pode colocar a lógica para salvar o cache,
        // filtrando rotas que não tenham Closures, por exemplo.

        $routesToCache = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $regex => $route) {
                if (is_callable($route['handler']) && !is_string($route['handler'])) {
                    continue; // Ignora closures
                }
                $routesToCache[$method][$regex] = $route;
            }
        }

        $export = var_export($routesToCache, true);
        $cacheContent = "<?php\n\nreturn {$export};\n";

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->cacheFile, $cacheContent);
    }

    /**
     * Tenta carregar rotas do cache
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


    // Métodos HTTP

    public function get(string $route, callable|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("GET", $route, $handler, $name, $middleware);
    }

    public function post(string $route, callable|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("POST", $route, $handler, $name, $middleware);
    }

    public function put(string $route, callable|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("PUT", $route, $handler, $name, $middleware);
    }

    public function patch(string $route, callable|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("PATCH", $route, $handler, $name, $middleware);
    }

    public function delete(string $route, callable|string $handler, string $name = null, array|string $middleware = null): void
    {
        $this->addRoute("DELETE", $route, $handler, $name, $middleware);
    }
}
