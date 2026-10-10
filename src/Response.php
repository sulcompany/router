<?php

declare(strict_types=1);

namespace SulCompany\Router;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected mixed $body = null;
    protected bool $sent = false;

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(array $data, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'application/json; charset=utf-8');
        $this->body = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        return $this;
    }

    public function text(string $text, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->body = $text;
        return $this;
    }

    public function html(string $html, int $status = 200): self
    {
        $this->status($status);
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->body = $html;
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        return $this
            ->status($status)
            ->header('Location', $url);
    }

    public function back(int $status = 302): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (!str_contains($referer, $host)) {
            $referer = '/';
        }

        return $this
            ->status($status)
            ->header('Location', $referer);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function hasContent(): bool
    {
        return $this->body !== null || !empty($this->headers);
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->body !== null) {
            echo $this->body;
        }

        $this->sent = true;
    }


    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}