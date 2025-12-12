<?php
declare(strict_types=1);

namespace SulCompany\Router;

class Router
{
    protected string $projectUrl;
    protected array $routes = []; // method => [Route,...] (definition)
    protected array $nameIndex = []; // name => ['method'=>..., 'path'=>...]
    protected array $globalMiddlewares = [];
    protected ?string $currentGroup = null;
    protected ?array $currentGroupMiddleware = null;

    protected array $staticRoutes = [];   // method => ["/fixed/path" => Route]
    protected array $dynamicRoutes = [];  // method => [ ['regex'=>..., 'route'=>Route], ... ]

    public bool $cacheEnabled = false;
    protected ?string $cacheFile = null;

    public function __construct(string $projectUrl)
    {
        $this->projectUrl = rtrim($projectUrl, '/');
    }

    // ---- public API (route registration) ----
    public function get(string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        return $this->addRoute('GET', $path, $handler, $name, $middleware);
    }

    public function post(string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        return $this->addRoute('POST', $path, $handler, $name, $middleware);
    }

    public function put(string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        return $this->addRoute('PUT', $path, $handler, $name, $middleware);
    }

    public function patch(string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        return $this->addRoute('PATCH', $path, $handler, $name, $middleware);
    }

    public function delete(string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        return $this->addRoute('DELETE', $path, $handler, $name, $middleware);
    }

    protected function addRoute(string $method, string $path, callable|array $handler, ?string $name = null, array|string $middleware = null): self
    {
        $path = $this->normalizePath($path);
        // apply group prefix at registration time so lookup is simpler later
        $fullPath = $this->applyGroupPrefixAtRegistration($path);

        [$handlerValue, $action] = $this->parseHandler($handler);
        $middlewares = $this->resolveRouteMiddlewares($middleware);

        $route = new Route($method, $fullPath, $handlerValue, $action, $name, $middlewares);

        $this->routes[$method][] = $route;

        if ($name) {
            $this->nameIndex[$name] = ['method' => $method, 'path' => $fullPath];
        }

        // keep compiled structures invalidated — compile() will rebuild
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];

        return $this;
    }

    // ---- group support ----
    public function group(string $prefix, callable $callback, array|string $middleware = null): self
    {
        $previousGroup = $this->currentGroup;
        $previousMiddleware = $this->currentGroupMiddleware;

        $this->currentGroup = trim($prefix, '/');
        $this->currentGroupMiddleware = $middleware ? (array)$middleware : null;

        $callback($this);

        $this->currentGroup = $previousGroup;
        $this->currentGroupMiddleware = $previousMiddleware;

        return $this;
    }

    protected function applyGroupPrefixAtRegistration(string $path): string
    {
        if ($this->currentGroup) {
            $prefix = '/' . trim($this->currentGroup, '/');
            if ($path === '/') {
                return $prefix;
            }
            return $prefix . ($path === '/' ? '' : $path);
        }
        return $path;
    }

    // ---- middleware ----
    public function addGlobalMiddleware(array|string $middleware): self
    {
        $this->globalMiddlewares = array_merge($this->globalMiddlewares, (array)$middleware);
        return $this;
    }

    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    protected function resolveRouteMiddlewares(array|string|null $routeMiddleware): array
    {
        $routeMw = $routeMiddleware ? (array)$routeMiddleware : [];
        $groupMw = $this->currentGroupMiddleware ?? [];
        return array_merge($this->globalMiddlewares, $groupMw, $routeMw);
    }

    // ---- cache ----
    public function enableCache(string $cacheFile): self
    {
        $this->cacheEnabled = true;
        $this->cacheFile = $cacheFile;
        return $this;
    }

    public function loadCache(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile || !file_exists($this->cacheFile)) {
            return false;
        }

        $cached = require $this->cacheFile;
        if (!is_array($cached)) return false;

        // rebuild Route objects from cached metadata
        $this->staticRoutes = [];
        foreach ($cached['static'] ?? [] as $method => $map) {
            foreach ($map as $path => $meta) {
                $r = $this->routeFromMeta($meta);
                $this->staticRoutes[$method][$path] = $r;
            }
        }

        $this->dynamicRoutes = [];
        foreach ($cached['dynamic'] ?? [] as $method => $list) {
            foreach ($list as $entry) {
                $r = $this->routeFromMeta($entry['route']);
                $r->regex = $entry['regex'] ?? $r->regex;
                $r->paramNames = $entry['paramNames'] ?? $r->paramNames;
                $this->dynamicRoutes[$method][] = ['regex' => $entry['regex'], 'route' => $r];
            }
        }

        $this->nameIndex = $cached['names'] ?? [];

        return true;
    }

    public function cacheCompiledRoutes(): void
    {
        $static = [];
        foreach ($this->staticRoutes as $method => $map) {
            foreach ($map as $path => $route) {
                if ($this->isClosure($route->handler)) continue;
                $static[$method][$path] = $this->serializeRouteMeta($route);
            }
        }

        $dynamic = [];
        foreach ($this->dynamicRoutes as $method => $list) {
            foreach ($list as $entry) {
                $route = $entry['route'];
                if ($this->isClosure($route->handler)) continue;
                $dynamic[$method][] = [
                    'regex' => $entry['regex'],
                    'route' => $this->serializeRouteMeta($route),
                    'paramNames' => $route->paramNames
                ];
            }
        }

        $export = var_export(['static' => $static, 'dynamic' => $dynamic, 'names' => $this->nameIndex], true);
        $content = "<?php\n\nreturn {$export};\n";

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($this->cacheFile, $content);
    }

    protected function routeFromMeta(array $meta): Route
    {
        $handler = $meta['handler'] ?? null;
        $route = new Route($meta['method'], $meta['path'], $handler, $meta['action'] ?? null, $meta['name'] ?? null, $meta['middlewares'] ?? []);
        $route->regex = $meta['regex'] ?? null;
        $route->paramNames = $meta['paramNames'] ?? [];
        return $route;
    }

    protected function serializeRouteMeta(Route $route): array
    {
        return [
            'method' => $route->method,
            'path' => $route->path,
            'handler' => is_string($route->handler) ? $route->handler : null,
            'action' => $route->action,
            'name' => $route->name,
            'middlewares' => $route->middlewares,
            'regex' => $route->regex,
            'paramNames' => $route->paramNames,
        ];
    }

    // ---- compile (build optimized lookup structures) ----
    public function compile(): void
    {
        // if staticRoutes already built and not empty, skip
        if (!empty($this->staticRoutes) || !empty($this->dynamicRoutes)) {
            return;
        }

        $this->staticRoutes = [];
        $this->dynamicRoutes = [];

        foreach ($this->routes as $method => $routesArray) {
            foreach ($routesArray as $route) {
                $path = $route->path;

                if ($this->isStaticRoutePath($path)) {
                    $this->staticRoutes[$method][$path] = $route;
                } else {
                    [$regex, $paramNames] = $this->compileRegex($path);
                    $route->regex = $regex;
                    $route->paramNames = $paramNames;
                    $this->dynamicRoutes[$method][] = ['regex' => $regex, 'route' => $route];
                }
            }
        }
    }

    // ---- url generation ----
    public function urlFor(string $name, array $params = [], array $query = []): string
    {
        if (!isset($this->nameIndex[$name])) {
            throw new \InvalidArgumentException("Named route '{$name}' not found.");
        }

        $info = $this->nameIndex[$name];
        $path = $info['path'];

        $url = $path;
        foreach ($params as $k => $v) {
            $placeholder = '{' . $k . '}';
            $wild = '{' . $k . '*}';
            if (str_contains($url, $wild)) {
                $replacement = is_array($v) ? implode('/', $v) : (string)$v;
                $url = str_replace($wild, $replacement, $url);
            } else {
                $url = str_replace($placeholder, (string)$v, $url);
            }
        }

        $url = preg_replace('~\{[a-zA-Z_][a-zA-Z0-9_-]*\*?\}~', '', $url);
        $queryString = empty($query) ? '' : '?' . http_build_query($query);
        return $this->projectUrl . $url . $queryString;
    }

    // ---- helpers ----
    protected function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return '/' . ($path === '' ? '' : $path);
    }

    protected function isStaticRoutePath(string $path): bool
    {
        return strpos($path, '{') === false;
    }

    protected function compileRegex(string $route): array
    {
        preg_match_all('~\{([a-zA-Z_][a-zA-Z0-9_-]*\*?)\}~', $route, $paramMatches);
        $paramNames = $paramMatches[1] ?? [];

        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\*\}~', '(.+)', $route);
        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\}~', '([^/]+)', $regex);

        return ["~^{$regex}$~", $paramNames];
    }

    protected function parseHandler(callable|array $handler): array
    {
        if (is_callable($handler) && !is_array($handler)) {
            return [$handler, null];
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;
            if (!is_string($controller) || !class_exists($controller)) {
                throw new \InvalidArgumentException("Controller inválido: '{$controller}'.");
            }
            if (!is_string($method) || $method === '') {
                throw new \InvalidArgumentException("Método do controller inválido; deve ser string não vazia.");
            }
            return [$controller, $method];
        }

        throw new \InvalidArgumentException("Handler inválido. Use closure ou [Controller::class, 'method']");
    }

    protected function isClosure($handler): bool
    {
        return is_object($handler) && ($handler instanceof \Closure);
    }

    protected function isStringCallable($h): bool
    {
        return is_string($h) && strpos($h, '::') !== false;
    }

    // ---- compatibility wrappers & accessors ----
    public function dispatch(): bool
    {
        $dispatcher = new Dispatcher($this);
        return $dispatcher->dispatch();
    }

    public function getStaticRoutes(): array
    {
        return $this->staticRoutes;
    }

    public function getDynamicRoutes(): array
    {
        return $this->dynamicRoutes;
    }

    public function getNameIndex(): array
    {
        return $this->nameIndex;
    }

    public function getProjectUrl(): string
    {
        return $this->projectUrl;
    }
}
