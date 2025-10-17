<?php

namespace SulCompany\Router;

trait RouterTrait
{
    /**
     * Registra uma nova rota
     */
    protected function addRoute(
        string $method,
        string $route,
        callable|string $handler,
        string $name = null,
        array|string $middleware = null
    ): void {
        $route = $this->normalizeRoute($route);
        $this->formSpoofing();

        [$regex, $paramNames] = $this->compileRegex($route);
        $parsedHandler = $this->parseHandler($handler);
        $effectiveMiddleware = $middleware ?? ($this->middleware[$this->group] ?? null);

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
     * Normaliza a rota, aplicando prefixo de grupo e removendo barras extras
     */
    private function normalizeRoute(string $route): string
    {
        $route = trim($route, "/");
        $route = $this->group ? "{$this->group}/{$route}" : $route;
        return "/" . trim($route, "/");
    }

    /**
     * Compila rota para regex e extrai nomes de parâmetros
     *
     * @return array{0: string, 1: array}
     */
    private function compileRegex(string $route): array
    {
        // Match: {param} e {param*}
        preg_match_all('~\{([a-zA-Z_][a-zA-Z0-9_-]*\*?)\}~', $route, $paramMatches);
        $paramNames = $paramMatches[1] ?? [];

        // Substitui {param*} por (.+) (múltiplos segmentos)
        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\*\}~', '(.+)', $route);

        // Substitui {param} por ([^/]+) (um único segmento)
        $regex = preg_replace('~\{([a-zA-Z_][a-zA-Z0-9_-]*)\}~', '([^/]+)', $regex);

        return ["~^{$regex}$~", $paramNames];
    }

    /**
     * Analisa handler e extrai controlador, método e nome qualificado
     *
     * @return array{fqcn?: string, callable?: callable, method?: string}
     */
    private function parseHandler(callable|string $handler): array
    {
        if (is_callable($handler)) {
            return ['callable' => $handler];
        }

        [$controller, $method] = explode($this->separator, $handler) + [null, null];

        return [
            'controller' => $controller,
            'method' => $method,
            'fqcn' => $this->namespace ? "{$this->namespace}\\{$controller}" : $controller,
        ];
    }

    /**
     * Suporte a form spoofing: _method
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

        // Remove o _method do POST se existir
        unset($postData['_method']);

        // Consolida tudo num só array
        $this->data = array_merge($queryData, $postData, $jsonData);

        // Força o método HTTP correto para spoofing
        $this->httpMethod = $method;
    }


    /**
     * Executa rota encontrada
     */
    private function execute(): bool
    {
        if (!$this->route) {
            $this->error = Dispatch::NOT_FOUND;
            return false;
        }

        if (!$this->middleware()) {
            return false;
        }

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
     * Executa middlewares
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

            if (!$instance->handle($this)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gera rota com dados tratados (para named routes, por exemplo)
     */
    private function treat(array $routeItem, ?array $data = null): ?string
    {
        $route = $routeItem["route"];

        if (!empty($data)) {
            $arguments = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (!str_contains($route, "{{$key}}")) {
                    $params[$key] = $value;
                }
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
