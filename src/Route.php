<?php

declare(strict_types=1);

namespace SulCompany\Router;

final class Route
{
    public string $method;
    public string $path; // e.g. /users/{id}
    public $handler; // closure or controller class string
    public ?string $action; // controller method name or null for closure
    public ?string $name;
    public array $middlewares;
    public ?string $regex = null;
    public array $paramNames = [];

    public function __construct(
        string $method,
        string $path,
        $handler,
        ?string $action = null,
        ?string $name = null,
        array $middlewares = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->action = $action;
        $this->name = $name;
        $this->middlewares = $middlewares;
    }

    public function isStatic(): bool
    {
        return strpos($this->path, '{') === false;
    }
}
