<?php

namespace SulCompany\Router;

trait RouterTrait
{
    /**
     * Adiciona uma nova rota
     */
    protected function addRoute(
        string $method,
        string $route,
        callable|array|string $handler,
        string $name = null,
        array|string $middleware = null
    ): void {
        $route = $this->normalizeRoute($route);
        $this->formSpoofing();

        [$regex, $paramNames] = $this->compileRegex($route);
        $parsedHandler = $this->parseHandler($handler);

        // Middlewares: rota > grupo > global
        $routeMiddleware = (array) ($middleware ?? ($this->middleware[$this->group] ?? []));
        $globalMiddleware = $this instanceof Router ? $this->getGlobalMiddlewares() : [];
        $effectiveMiddleware = array_merge($globalMiddleware, $routeMiddleware);

        $this->routes[$method][$regex] = [
            "route" => $route,
            "name" => $name,
            "method" => $method,
            "handler" => $parsedHandler['fqcn'] ?? $parsedHandler['callable'],
            "action" => $parsedHandler['method'] ?? null,
            "middlewares" => $effectiveMiddleware,
            "__paramNames" => $paramNames
        ];
    }

    /**
     * Normaliza a rota, aplicando grupo e removendo barras extras
     */
    private function normalizeRoute(string $route): string
    {
        $route = trim($route, "/");
        $route = $this->group ? "{$this->group}/{$route}" : $route;
        return "/" . trim($route, "/");
    }

    /**
     * Compila a rota em regex e extrai nomes dos parâmetros
     */
    private function compileRegex(string $route): array
    {
        preg_match_all('~\{([a-zA-Z_][a-zA-Z0-9_-]*\*?)\}~', $route, $paramMatches);
        $paramNames = $paramMatches[1] ?? [];

        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\*\}~', '(.+)', $route);
        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\}~', '([^/]+)', $regex);

        return ["~^{$regex}$~", $paramNames];
    }

    /**
     * Analisa o handler e retorna array com fqcn e método
     */
    private function parseHandler(callable|array|string $handler): array
    {
        if (is_callable($handler)) return ['callable' => $handler];
        if (is_array($handler) && count($handler) === 2) return ['fqcn' => $handler[0], 'method' => $handler[1]];

        [$controller, $method] = explode($this->separator, $handler) + [null, null];

        return [
            'controller' => $controller,
            'method' => $method,
            'fqcn' => $this->namespace ? "{$this->namespace}\\{$controller}" : $controller,
        ];
    }

    /**
     * Suporte a spoofing de método (_method)
     */
    protected function formSpoofing(): void
    {
        $postData = filter_input_array(INPUT_POST, FILTER_DEFAULT) ?? [];

        $jsonData = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $jsonData = json_decode($raw, true) ?? [];
        }

        $queryData = filter_input_array(INPUT_GET, FILTER_DEFAULT) ?? [];

        $method = strtoupper($postData['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        unset($postData['_method']);

        $this->data = array_merge($queryData, $postData, $jsonData);
        $this->httpMethod = $method;
    }

    /**
     * Executa a rota ativa
     */
    private function execute(): bool
    {
        if (!$this->route) {
            $this->error = Dispatch::NOT_FOUND;
            return false;
        }

        if (!$this->middleware()) return false;

        if (is_callable($this->route['handler'])) {
            call_user_func($this->route['handler'], $this->route['data'] ?? [], $this);
            return true;
        }

        $controller = $this->route['handler'];
        $method = $this->route['action'];

        if (!class_exists($controller)) {
            $this->error = Dispatch::BAD_REQUEST;
            return false;
        }

        $instance = new $controller($this);

        if (!method_exists($instance, $method)) {
            $this->error = Dispatch::METHOD_NOT_ALLOWED;
            return false;
        }

        $instance->$method($this->route['data'] ?? []);
        return true;
    }

    /**
     * Executa middlewares (global, grupo ou rota)
     */
    private function middleware(): bool
    {
        $middlewares = (array) ($this->route["middlewares"] ?? []);

        foreach ($middlewares as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                $this->error = Dispatch::NOT_IMPLEMENTED;
                return false;
            }

            $instance = new $middlewareClass;

            if (!method_exists($instance, "handle")) {
                $this->error = Dispatch::METHOD_NOT_ALLOWED;
                return false;
            }

            if (!$instance->handle($this)) return false;
        }

        return true;
    }

    /**
     * Gera URL tratada (named routes)
     */
    private function treat(array $routeItem, ?array $data = null): ?string
    {
        $route = $routeItem["route"];

        if (!empty($data)) {
            $arguments = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (!str_contains($route, "{{$key}}")) $params[$key] = $value;
                $arguments["{{$key}}"] = $value;
            }

            $route = $this->process($route, $arguments, $params);
        }

        return "{$this->projectUrl}{$route}";
    }

    /**
     * Substitui argumentos e adiciona query string
     */
    private function process(string $route, array $arguments, ?array $params = null): string
    {
        $query = (!empty($params) ? "?" . http_build_query($params) : "");
        return str_replace(array_keys($arguments), array_values($arguments), $route) . $query;
    }
}
