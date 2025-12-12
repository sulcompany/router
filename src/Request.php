<?php

declare(strict_types=1);

namespace SulCompany\Router;

class Request
{
    protected array $get;
    protected array $post;
    protected array $json;
    protected array $all;
    protected array $headers;
    protected string $method;
    protected string $path;

    public function __construct(
        array $get = [],
        array $post = [],
        array $json = [],
        string $method = 'GET',
        string $path = '/'
    ) {
        $this->get = $get;
        $this->post = $post;
        $this->json = $json;
        $this->method = strtoupper($method);
        $this->path = $path;

        $this->all = array_merge($get, $post, $json);
        $this->headers = $this->extractHeaders();
    }

    protected function extractHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function header(string $name = null) {
        if ($name === null) return $this->headers;
        $name = strtolower($name);
        return $this->headers[$name] ?? null;
    }

    public function get(string $key = null, $default = null)
    {
        if ($key === null) return $this->get;
        return $this->get[$key] ?? $default;
    }

    public function post(string $key = null, $default = null)
    {
        if ($key === null) return $this->post;
        return $this->post[$key] ?? $default;
    }

    public function json(string $key = null, $default = null)
    {
        if ($key === null) return $this->json;
        return $this->json[$key] ?? $default;
    }

    public function all(string $key = null, $default = null)
    {
        if ($key === null) return $this->all;
        return $this->all[$key] ?? $default;
    }
}
