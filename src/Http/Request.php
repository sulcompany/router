<?php
namespace SulCompany\Http;

class Request
{
    private array $data;
    private array $query;
    private array $post;
    private array $json;

    public function __construct(array $data = [], array $query = [], array $post = [], array $json = [])
    {
        $this->data = $data;
        $this->query = $query;
        $this->post = $post;
        $this->json = $json;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function post(): array
    {
        return $this->post;
    }

    public function json(): array
    {
        return $this->json;
    }
}
