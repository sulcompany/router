<?php

declare(strict_types=1);

namespace SulCompany\Router;

class Route
{
    public string $method;
    public string $path;
    public $handler;
    public ?string $action;
    public ?string $name;
    public array $middlewares;
    public ?string $regex = null;
    public array $paramNames = [];

    private array $attributes = [];

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

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }
}